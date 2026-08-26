<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Http\Controllers\XtreamController;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Source health checker with automatic backup failover.
 *
 * Probes the channel's source URL to determine if it's reachable.
 * When the primary source goes down, automatically tries backup URLs
 * and switches the ingest to whichever backup is online.
 */
class SourceHealthCheckService
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_SECONDS = 5;
    private const CHECK_TIMEOUT_SECONDS = 10;
    private const LOCK_TTL_SECONDS = 30;

    /**
     * Check the health of a channel's currently active source URL.
     */
    public function checkSource(Channel $channel): array
    {
        $lockKey = "source:health:lock:{$channel->id}";
        $lock = Cache::lock($lockKey, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            return [
                'status' => 'checking',
                'message' => 'Health check already in progress',
                'details' => [],
            ];
        }

        try {
            $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;
            $result = $this->probeUrl($sourceUrl, $channel);

            $channel->update([
                'source_status' => $result['status'],
                'source_last_checked_at' => now(),
                'source_last_error' => $result['message'] ?? null,
                'source_check_attempts' => $result['status'] === 'online' ? 0 : ($channel->source_check_attempts + 1),
                'source_last_online_at' => $result['status'] === 'online' ? now() : $channel->source_last_online_at,
            ]);

            Log::info('Source health check completed', [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'status' => $result['status'],
                'message' => $result['message'],
                'source_url' => $sourceUrl,
                'active_index' => $channel->active_source_index,
                'attempts' => $channel->source_check_attempts,
            ]);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Check source health, restart if offline, and try backups if restart fails.
     */
    public function checkAndRefresh(Channel $channel): array
    {
        $result = $this->checkSource($channel);

        if ($result['status'] === 'online') {
            return $result;
        }

        // First: try restarting with the current active source
        $restartResult = $this->restartWithRetry($channel, $channel->active_stream_url ?? $channel->stream_url);

        if ($restartResult['success']) {
            return array_merge($result, ['restart' => $restartResult]);
        }

        // If restart failed, try backup URLs
        $failoverResult = $this->tryBackupUrls($channel);

        return array_merge($result, [
            'restart' => $restartResult,
            'failover' => $failoverResult,
        ]);
    }

    /**
     * Manually refresh (restart) a channel's ingest.
     * Non-blocking: kills the old ingest, starts a new one, returns immediately.
     * No retry loop, no post-check probe — the wrapper script and watchdog
     * handle recovery. This prevents PHP-FPM worker exhaustion which causes
     * ALL channels' HLS requests to time out.
     */
    public function manualRefresh(Channel $channel): array
    {
        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        Log::info('Manual refresh (fast)', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'source_url' => $sourceUrl,
        ]);

        $xtream = app(XtreamController::class);
        $xtream->ensureHlsStream(
            (int) $channel->id,
            $sourceUrl,
            $channel->program_number,
            $channel->local_address
        );

        $channel->update([
            'source_status' => 'online',
            'source_last_checked_at' => now(),
            'source_last_error' => null,
            'source_last_online_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Channel refresh initiated — ingest restarted',
        ];
    }

    /**
     * Manually switch to a specific source index (0=primary, 1=backup1, 2=backup2).
     */
    public function switchSource(Channel $channel, int $sourceIndex): array
    {
        $urls = $channel->getSourceUrls();

        if (!isset($urls[$sourceIndex])) {
            return ['success' => false, 'message' => 'Invalid source index'];
        }

        $url = $urls[$sourceIndex];
        $labels = ['Primary', 'Backup 1', 'Backup 2'];

        // Kill existing ingest
        $this->stopChannel($channel);
        sleep(1);

        // Update DB to point to the new source
        $channel->update([
            'active_stream_url' => $url,
            'active_source_index' => $sourceIndex,
            'source_check_attempts' => 0,
        ]);

        // Start ingest with the new source
        $xtream = app(XtreamController::class);
        $xtream->ensureHlsStream(
            (int) $channel->id,
            $url,
            $channel->program_number,
            $channel->local_address
        );

        sleep(3);

        // Verify it's working
        $probe = $this->probeUrl($url, $channel);

        $channel->update([
            'source_status' => $probe['status'],
            'source_last_checked_at' => now(),
            'source_last_error' => $probe['message'] ?? null,
            'source_check_attempts' => $probe['status'] === 'online' ? 0 : 1,
            'source_last_online_at' => $probe['status'] === 'online' ? now() : $channel->source_last_online_at,
        ]);

        Log::info('Manual source switch', [
            'channel_id' => $channel->id,
            'from_index' => $channel->active_source_index,
            'to_index' => $sourceIndex,
            'label' => $labels[$sourceIndex] ?? "Index {$sourceIndex}",
            'url' => $url,
            'result' => $probe['status'],
        ]);

        return [
            'success' => $probe['status'] === 'online',
            'source_index' => $sourceIndex,
            'label' => $labels[$sourceIndex] ?? "Index {$sourceIndex}",
            'url' => $url,
            'probe' => $probe,
        ];
    }

    /**
     * Try backup URLs in order. If one is online, switch the ingest to it.
     */
    public function tryBackupUrls(Channel $channel): array
    {
        $currentIndex = $channel->active_source_index;
        $backupUrls = [];

        // Build list of backup URLs to try (skip already-tried sources)
        if ($currentIndex < 1 && !empty($channel->backup_url_1)) {
            $backupUrls[] = ['index' => 1, 'url' => $channel->backup_url_1];
        }
        if ($currentIndex < 2 && !empty($channel->backup_url_2)) {
            $backupUrls[] = ['index' => 2, 'url' => $channel->backup_url_2];
        }

        // Also try primary if we're currently on a backup
        if ($currentIndex > 0 && !empty($channel->stream_url)) {
            $backupUrls[] = ['index' => 0, 'url' => $channel->stream_url];
        }

        $labels = ['Primary', 'Backup 1', 'Backup 2'];

        foreach ($backupUrls as ['index' => $idx, 'url' => $url]) {
            Log::info("Trying backup source for channel {$channel->id}", [
                'source_index' => $idx,
                'label' => $labels[$idx] ?? "Index {$idx}",
                'url' => $url,
            ]);

            $probe = $this->probeUrl($url, $channel);

            if ($probe['status'] === 'online') {
                Log::info("Backup source is online, switching channel {$channel->id}", [
                    'from_index' => $currentIndex,
                    'to_index' => $idx,
                    'url' => $url,
                ]);

                // Kill existing ingest
                $this->stopChannel($channel);
                sleep(1);

                // Switch to the working backup
                $channel->update([
                    'active_stream_url' => $url,
                    'active_source_index' => $idx,
                    'source_check_attempts' => 0,
                    'source_status' => 'online',
                    'source_last_checked_at' => now(),
                    'source_last_online_at' => now(),
                    'source_last_error' => null,
                ]);

                // Start ingest with the backup URL
                $xtream = app(XtreamController::class);
                $xtream->ensureHlsStream(
                    (int) $channel->id,
                    $url,
                    $channel->program_number,
                    $channel->local_address
                );

                return [
                    'success' => true,
                    'switched_to' => $idx,
                    'label' => $labels[$idx] ?? "Index {$idx}",
                    'url' => $url,
                    'message' => "Switched to " . ($labels[$idx] ?? "Index {$idx}") . ": {$url}",
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'All backup sources are offline',
        ];
    }

    /**
     * Stop a channel's ingest process.
     */
    public function stopChannel(Channel $channel): bool
    {
        $outputDir = storage_path("app/streams/hls/{$channel->id}");
        $pidFile = $outputDir . '/ingest.pid';

        $stopped = false;

        if (is_dir($outputDir)) {
            file_put_contents($outputDir . '/.stop', '1');
        }

        if (is_file($pidFile)) {
            $pid = (int) trim((string) file_get_contents($pidFile));

            if ($pid > 0) {
                $marker = 'streams/hls/' . $channel->id . '/playlist.m3u8';
                @exec('pkill -TERM -f ' . escapeshellarg($marker) . ' 2>/dev/null');
                usleep(500000);
                @exec('pkill -KILL -f ' . escapeshellarg($marker) . ' 2>/dev/null');

                @exec("kill -TERM -{$pid} 2>/dev/null");
                usleep(500000);
                @exec("kill -KILL -{$pid} 2>/dev/null");

                $stopped = true;
            }

            @unlink($pidFile);
        }

        cache()->forget("ffmpeg:channel:{$channel->id}");
        cache()->forget("ffmpeg:ingest:{$channel->id}");

        Log::info('Channel stopped', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
        ]);

        return $stopped;
    }

    /**
     * Probe a URL using ffprobe.
     * For UDP multicast, checks the ingest process + segments instead
     * (ffprobe on multicast is unreliable — it must join the group and
     * can timeout even when the source is healthy).
     */
    private function probeUrl(string $url, Channel $channel): array
    {
        if (empty($url)) {
            return [
                'status' => 'offline',
                'message' => 'No stream URL configured',
                'details' => [],
            ];
        }

        $isMulticast = str_starts_with($url, 'udp://') || str_starts_with($url, 'rtp://');

        // For UDP multicast: check the ingest wrapper process and segment
        // freshness instead of running ffprobe (which would need to re-join
        // the multicast group and can take 10+ seconds).
        if ($isMulticast) {
            return $this->checkMulticastIngest($channel);
        }

        $isHls = in_array($channel->stream_type, ['hls', 'm3u8']) || str_contains(strtolower($url), '.m3u8');

        $timeout = self::CHECK_TIMEOUT_SECONDS;
        $rwTimeout = $isHls ? '-rw_timeout 10000000' : '';
        $inputUrl = escapeshellarg($url);

        $command = sprintf(
            'timeout %d ffprobe -v error -show_streams -show_format -of json -analyzeduration 5M -probesize 5M %s %s 2>&1; echo "EXIT:$?"',
            $timeout,
            $rwTimeout,
            $inputUrl
        );

        $output = shell_exec($command . ' 2>&1');

        $exitCode = 1;
        if (preg_match('/EXIT:(\d+)\s*$/', $output, $m)) {
            $exitCode = (int) $m[1];
            $output = preg_replace('/EXIT:\d+\s*$/', '', $output);
        }

        $data = json_decode(trim($output), true);
        $hasStreamData = is_array($data) && (!empty($data['streams']) || !empty($data['format']));

        if ($exitCode === 0 && $hasStreamData) {
            $details = $this->extractStreamDetails($data);

            return [
                'status' => 'online',
                'message' => 'Source is online and streaming',
                'details' => $details,
            ];
        }

        $errorOutput = trim($output);
        $message = $this->parseError($errorOutput);

        return [
            'status' => 'offline',
            'message' => $message,
            'details' => [],
        ];
    }

    /**
     * Check a multicast channel by inspecting its ingest process and
     * segment freshness rather than probing the source URL directly.
     */
    private function checkMulticastIngest(Channel $channel): array
    {
        $outputDir = storage_path("app/streams/hls/{$channel->id}");
        $pidFile  = $outputDir . '/ingest.pid';
        // MyChannel playout writes index.m3u8; multicast ingest writes playlist.m3u8
        $playlist = is_file($outputDir . '/index.m3u8')
            ? $outputDir . '/index.m3u8'
            : $outputDir . '/playlist.m3u8';

        if (! is_file($pidFile)) {
            return [
                'status' => 'offline',
                'message' => 'No ingest process running',
                'details' => [],
            ];
        }

        $pid = (int) trim((string) file_get_contents($pidFile));

        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            return [
                'status' => 'offline',
                'message' => 'Ingest process dead (PID ' . $pid . ')',
                'details' => [],
            ];
        }

        // Process is alive — check segment freshness
        if (! is_file($playlist)) {
            // Wrapper is alive but hasn't produced segments yet — give it time
            $age = time() - filemtime($outputDir);
            if ($age < 30) {
                return [
                    'status' => 'online',
                    'message' => 'Ingest starting up (no playlist yet)',
                    'details' => [],
                ];
            }
            return [
                'status' => 'offline',
                'message' => 'Ingest alive but no playlist after ' . $age . 's',
                'details' => [],
            ];
        }

        $mtime = @filemtime($playlist);
        $age = $mtime ? (time() - $mtime) : PHP_INT_MAX;

        if ($age > 30) {
            return [
                'status' => 'offline',
                'message' => 'Playlist stale (' . $age . 's old)',
                'details' => [],
            ];
        }

        return [
            'status' => 'online',
            'message' => 'Ingest running, segments fresh (' . $age . 's)',
            'details' => [],
        ];
    }

    /**
     * Probe a URL for audio streams (used by AutoCheckSourceHealth).
     * Returns false for UDP multicast (audio status is checked via ingest logs).
     */
    public function probeForAudio(string $url, Channel $channel): bool
    {
        if (empty($url)) {
            return false;
        }

        $isMulticast = str_starts_with($url, 'udp://') || str_starts_with($url, 'rtp://');
        if ($isMulticast) {
            return true; // Skip audio probe for multicast — trust the ingest
        }

        $inputUrl = escapeshellarg($url);

        $rwTimeout = '-rw_timeout 5000000';

        $command = sprintf(
            'timeout %d ffprobe -v error -select_streams a -show_entries stream=codec_type -of csv=p=0 %s %s 2>&1',
            self::CHECK_TIMEOUT_SECONDS,
            $rwTimeout,
            $inputUrl
        );

        $output = trim(shell_exec($command . ' 2>&1') ?: '');

        return str_contains($output, 'audio');
    }

    private function extractStreamDetails(array $data): array
    {
        $details = [];

        if (!empty($data['streams'])) {
            foreach ($data['streams'] as $stream) {
                if (($stream['codec_type'] ?? '') === 'video') {
                    $details['codec'] = $stream['codec_name'] ?? null;
                    $details['resolution'] = ($stream['width'] ?? '?') . 'x' . ($stream['height'] ?? '?');
                    $details['fps'] = $stream['r_frame_rate'] ?? null;
                    $details['bitrate'] = isset($stream['bit_rate']) ? (int) $stream['bit_rate'] : null;
                    break;
                }
            }
        }

        if (!empty($data['format'])) {
            $details['bitrate'] = $details['bitrate'] ?? (isset($data['format']['bit_rate']) ? (int) $data['format']['bit_rate'] : null);
            $details['format'] = $data['format']['format_name'] ?? null;
        }

        return $details;
    }

    private function parseError(string $errorOutput): string
    {
        $patterns = [
            'Failed to resolve hostname' => 'DNS resolution failed',
            'Name or service not known' => 'DNS resolution failed',
            'Could not resolve host' => 'DNS resolution failed',
            'Connection refused' => 'Connection refused',
            'Connection timed out' => 'Connection timed out',
            'timed out' => 'Connection timed out',
            'Server returned 404' => 'Source not found (HTTP 404)',
            '404 Not Found' => 'Source not found (HTTP 404)',
            'Server returned 403' => 'Access denied (HTTP 403)',
            '403 Forbidden' => 'Access denied (HTTP 403)',
            'Invalid data' => 'Invalid stream data',
            'no stream' => 'No stream data',
            'End of file' => 'Stream ended unexpectedly',
            'EOF' => 'Stream ended unexpectedly',
            'Input/output error' => 'I/O error',
            'No such file or directory' => 'Stream file not found',
            'Permission denied' => 'Permission denied',
            'No route to host' => 'Network unreachable',
            'Network is unreachable' => 'Network unreachable',
        ];

        foreach ($patterns as $pattern => $message) {
            if (str_contains($errorOutput, $pattern)) {
                return $message;
            }
        }

        $errorLines = array_filter(explode("\n", $errorOutput), fn ($l) => trim($l) !== '');
        return !empty($errorLines) ? trim(end($errorLines)) : 'Source is offline or unreachable';
    }

    /**
     * Restart the ingest with retry logic using a specific URL.
     */
    private function restartWithRetry(Channel $channel, string $sourceUrl): array
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts < self::MAX_RETRIES) {
            $attempts++;

            try {
                Log::info('Attempting source restart', [
                    'channel_id' => $channel->id,
                    'attempt' => $attempts,
                    'source_url' => $sourceUrl,
                ]);

                $this->stopChannel($channel);
                sleep(1);

                $xtream = app(XtreamController::class);
                $xtream->ensureHlsStream(
                    (int) $channel->id,
                    $sourceUrl,
                    $channel->program_number,
                    $channel->local_address
                );

                sleep(self::RETRY_DELAY_SECONDS);

                $check = $this->probeUrl($sourceUrl, $channel);

                if ($check['status'] === 'online') {
                    return [
                        'success' => true,
                        'attempts' => $attempts,
                        'message' => "Source restored after {$attempts} attempt(s)",
                    ];
                }

                $lastError = $check['message'];
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error('Source restart attempt failed', [
                    'channel_id' => $channel->id,
                    'attempt' => $attempts,
                    'error' => $lastError,
                ]);
            }

            if ($attempts < self::MAX_RETRIES) {
                sleep(self::RETRY_DELAY_SECONDS);
            }
        }

        return [
            'success' => false,
            'attempts' => $attempts,
            'message' => "Failed to restore source after {$attempts} attempts: {$lastError}",
        ];
    }
}
