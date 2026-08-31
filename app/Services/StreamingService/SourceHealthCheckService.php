<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Http\Controllers\XtreamController;
use App\Models\Channel;
use App\Services\StreamingService\MulticastIngestService;
use App\Services\YouTubeService;
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
     * Probe every configured source (primary, backup 1, backup 2) for a channel
     * and persist the per-source statuses. Lightweight for the UI: it uses the
     * same fast paths as the watchdog (sibling/group checks for multicast),
     * and only runs ffprobe for the HTTP sources.
     */
    public function probeAllSources(Channel $channel): array
    {
        $statuses = [];
        $labels   = ['Primary', 'Backup 1', 'Backup 2'];

        if ($channel->isYouTube()) {
            $urls = [
                0 => $channel->source_url ?: $channel->stream_url,
                1 => $channel->backup_url_1,
                2 => $channel->backup_url_2,
            ];
        } else {
            $urls = [
                0 => $channel->stream_url,
                1 => $channel->backup_url_1,
                2 => $channel->backup_url_2,
            ];
        }

        $oldStatuses = $channel->source_statuses instanceof \Illuminate\Support\Collection
            ? $channel->source_statuses->all()
            : ($channel->source_statuses ?? []);

        foreach (array_keys($urls) as $idx) {
            $url = $urls[$idx] ?? null;

            if (empty($url)) {
                $statuses[$idx] = [
                    'index'          => $idx,
                    'label'          => $labels[$idx],
                    'url'            => null,
                    'status'         => 'unconfigured',
                    'last_checked_at'=> null,
                    'last_online_at' => null,
                    'error'          => null,
                ];
                continue;
            }

            $result = $this->probeSourceUrl($url, $channel, $idx);

            $statuses[$idx] = [
                'index'          => $idx,
                'label'          => $labels[$idx],
                'url'            => $url,
                'status'         => $result['status'],
                'last_checked_at'=> now(),
                'last_online_at' => $result['status'] === 'online'
                    ? now()
                    : ($oldStatuses[$idx]['last_online_at'] ?? null),
                'error'          => $result['status'] === 'online'
                    ? null
                    : ($result['message'] ?? null),
            ];
        }

        $activeIdx = (int) ($channel->active_source_index ?? 0);
        $active    = $statuses[$activeIdx] ?? [];

        $channel->update([
            'source_statuses_json'  => $statuses,
            'source_status'         => $active['status'] ?? 'unknown',
            'source_last_checked_at'=> now(),
            'source_last_error'     => $active['error'] ?? null,
            'source_check_attempts' => ($active['status'] ?? '') === 'online' ? 0 : ((int) $channel->source_check_attempts + 1),
            'source_last_online_at' => ($active['status'] ?? '') === 'online' ? now() : $channel->source_last_online_at,
            'sources_last_probed_at'=> now(),
        ]);

        return $statuses;
    }

    /**
     * Probe a single source URL (not necessarily the active one).
     * Multicast uses the group/sibling fast path (no ffprobe); HTTP uses ffprobe.
     * YouTube sources use the YouTube service for verification.
     */
    public function probeSourceUrl(string $url, Channel $channel, int $index): array
    {
        if (empty($url)) {
            return ['status' => 'unconfigured', 'message' => 'No source URL configured', 'details' => []];
        }

        if ($channel->isYouTube() && $index === 0) {
            return $this->probeYouTubeSource($channel);
        }

        if ($channel->isYouTubeBackup($index) && !empty($url)) {
            $probe = $this->probeUrl($url, $channel);

            if ($probe['status'] !== 'online') {
                $youtubeUrlField = "youtube_url_{$index}";
                $youtubeUrl = $channel->{$youtubeUrlField} ?? null;

                if (!empty($youtubeUrl) && (str_contains($youtubeUrl, 'youtube.com') || str_contains($youtubeUrl, 'youtu.be'))) {
                    $ytService = new YouTubeService();
                    $freshUrl = $ytService->resolveUrlToStreamUrl($youtubeUrl);

                    if (!empty($freshUrl)) {
                        $backupField = "backup_url_{$index}";
                        $channel->update([$backupField => $freshUrl]);
                        $probe = $this->probeUrl($freshUrl, $channel);
                    }
                }
            }

            return $probe;
        }

        $isMulticast = str_starts_with($url, 'udp://') || str_starts_with($url, 'rtp://');

        if ($isMulticast) {
            $status = $this->probeOnly($url, $channel);
            return [
                'status'  => $status,
                'message' => $status === 'online'
                    ? 'Source is online and streaming'
                    : 'Source offline — no live group reader',
                'details' => [],
            ];
        }

        return $this->probeUrl($url, $channel);
    }

    /**
     * Probe a YouTube source using the YouTube service.
     * Always bypasses robot verification using stored cookies.
     */
    private function probeYouTubeSource(Channel $channel): array
    {
        $ytService = new YouTubeService();

        if (!$ytService->isYtDlpAvailable()) {
            return [
                'status' => 'offline',
                'message' => 'yt-dlp not available — cannot verify YouTube source',
                'details' => [],
            ];
        }

        if (!$channel->isYouTubeVerified()) {
            $result = $ytService->ensureVerified($channel);
            if (!$result['success']) {
                return [
                    'status' => 'offline',
                    'message' => 'YouTube verification failed: ' . ($result['error'] ?? 'unknown error'),
                    'details' => [],
                ];
            }
        }

        $streamUrl = $channel->source_url;

        if (empty($streamUrl)) {
            $resolveResult = $ytService->resolveToStreamUrl($channel);
            if (!$resolveResult['success']) {
                return [
                    'status' => 'offline',
                    'message' => 'Failed to resolve YouTube stream: ' . ($resolveResult['error'] ?? 'unknown'),
                    'details' => [],
                ];
            }
            $streamUrl = $resolveResult['stream_url'];
        }

        $probeResult = $this->probeUrl($streamUrl, $channel);

        if ($probeResult['status'] !== 'online' && !empty($channel->youtube_url)) {
            $resolveResult = $ytService->resolveToStreamUrl($channel);
            if ($resolveResult['success'] && !empty($resolveResult['stream_url'])) {
                $streamUrl = $resolveResult['stream_url'];
                $probeResult = $this->probeUrl($streamUrl, $channel);
            }
        }

        return [
            'status'  => $probeResult['status'],
            'message' => $probeResult['status'] === 'online'
                ? 'YouTube stream is online and streaming'
                : 'YouTube stream offline — ' . ($probeResult['message'] ?? 'unknown'),
            'details' => array_merge($probeResult['details'] ?? [], [
                'youtube_verified' => $channel->isYouTubeVerified(),
                'youtube_url' => $channel->youtube_url,
            ]),
        ];
    }

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

        // Mark as manual override so the watchdog won't auto-restore it.
        // TTL of 7 days — long enough to survive reboots; admin can clear by
        // switching back to primary manually or it expires naturally.
        if ($sourceIndex > 0) {
            Cache::put("channel:manual_override:{$channel->id}", true, now()->addDays(7));
        } else {
            Cache::forget("channel:manual_override:{$channel->id}");
        }

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

        // Record the newly-active source's real status for the UI chips.
        $statuses = $channel->source_statuses instanceof \Illuminate\Support\Collection
            ? $channel->source_statuses->all()
            : ($channel->source_statuses ?? []);

        $statuses[$sourceIndex] = [
            'index'          => $sourceIndex,
            'label'          => $labels[$sourceIndex] ?? "Index {$sourceIndex}",
            'url'            => $url,
            'status'         => $probe['status'],
            'last_checked_at'=> now(),
            'last_online_at' => $probe['status'] === 'online' ? now() : ($statuses[$sourceIndex]['last_online_at'] ?? null),
            'error'          => $probe['status'] === 'online' ? null : ($probe['message'] ?? null),
        ];

        $channel->update([
            'source_status' => $probe['status'],
            'source_last_checked_at' => now(),
            'source_last_error' => $probe['message'] ?? null,
            'source_check_attempts' => $probe['status'] === 'online' ? 0 : 1,
            'source_last_online_at' => $probe['status'] === 'online' ? now() : $channel->source_last_online_at,
            'source_statuses_json' => $statuses,
            'sources_last_probed_at' => now(),
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
     * Always tries ALL sources in priority order (Primary → Backup 1 → Backup 2),
     * skipping only the currently active one.
     */
    public function tryBackupUrls(Channel $channel): array
    {
        return $this->switchToBestAvailableSource($channel, skip: $channel->active_source_index);
    }

    /**
     * Try all sources in priority order and switch to the best available one.
     * Skips the source at $skip index (the currently active one).
     * Primary (0) is always tried first — so if on Backup 2 and Primary is up,
     * we jump straight back to Primary rather than stepping through Backup 1.
     */
    public function switchToBestAvailableSource(Channel $channel, int $skip = -1): array
    {
        $allSources = [
            0 => $channel->isYouTube() ? ($channel->source_url ?: $channel->stream_url) : $channel->stream_url,
            1 => $channel->backup_url_1,
            2 => $channel->backup_url_2,
        ];
        $labels = ['Primary', 'Backup 1', 'Backup 2'];

        $statuses = $channel->source_statuses instanceof \Illuminate\Support\Collection
            ? $channel->source_statuses->all()
            : ($channel->source_statuses ?? []);
        $now = now();
        $probedOnline = false;

        foreach ($allSources as $idx => $url) {
            if ($idx === $skip || empty($url)) {
                continue;
            }

            $probe = $this->probeSourceUrl($url, $channel, $idx);

            if ($idx === 0 && $channel->isYouTube() && $channel->source_url !== $url) {
                $url = $channel->source_url;
                $allSources[0] = $url;
            }

            if ($idx > 0 && $channel->isYouTubeBackup($idx)) {
                $freshUrl = $channel->fresh()->{"backup_url_{$idx}"} ?? $url;
                if ($freshUrl !== $url) {
                    $url = $freshUrl;
                    $allSources[$idx] = $url;
                }
            }

            // Persist the probe result for UI pollers.
            $statuses[$idx] = [
                'index'          => $idx,
                'label'          => $labels[$idx],
                'url'            => $url,
                'status'         => $probe['status'],
                'last_checked_at'=> $now,
                'last_online_at' => $probe['status'] === 'online' ? $now : ($channel->source_statuses[$idx]['last_online_at'] ?? null),
                'error'          => $probe['status'] === 'online' ? null : ($probe['message'] ?? null),
            ];

            if ($probe['status'] !== 'online') {
                continue;
            }

            $probedOnline = true;

            Log::info("Switching channel {$channel->id} to {$labels[$idx]}", [
                'from_index' => $channel->active_source_index,
                'to_index'   => $idx,
                'url'        => $url,
            ]);

            // Auto-failover clears any manual override so the watchdog can
            // restore to primary when it recovers.
            Cache::forget("channel:manual_override:{$channel->id}");

            $this->doSwitch($channel, $idx, $url);

            $channel->update(['source_statuses_json' => $statuses]);

            return [
                'success'    => true,
                'switched_to'=> $idx,
                'label'      => $labels[$idx],
                'url'        => $url,
                'message'    => "Switched to {$labels[$idx]}: {$url}",
            ];
        }

        if ($probedOnline || $statuses) {
            $channel->update(['source_statuses_json' => $statuses]);
        }

        return [
            'success' => false,
            'message' => 'All alternative sources are offline',
        ];
    }

    /**
     * Core switch logic shared by manual and auto-failover paths.
     * Order matters:
     *  1. Stop the current ingest (per-channel wrapper or group reader output)
     *  2. Update DB — getChannelGroups() now excludes this channel if going to backup
     *  3. Restart group reader without this channel (if leaving multicast)
     *  4. Start new ingest on the target URL
     */
    private function doSwitch(Channel $channel, int $toIndex, string $toUrl): void
    {
        $primaryUrl = $channel->stream_url;
        $leavingMulticast = str_starts_with($primaryUrl, 'udp://') || str_starts_with($primaryUrl, 'rtp://');

        // 1. Stop current ingest. For multicast channels on primary, kill the
        //    group reader using the primary URL (active_stream_url may already
        //    differ if called from a retry path).
        if ($leavingMulticast && (int) $channel->active_source_index === 0) {
            $multicast = app(MulticastIngestService::class);
            $multicast->stopGroupByPrimaryUrl($primaryUrl);
        } else {
            $this->stopChannel($channel);
        }
        sleep(1);

        // 2. Update DB — must happen before restartGroupExcluding so
        //    getChannelGroups() sees the new active_source_index and excludes
        //    this channel from the rebuilt group reader.
        $channel->update([
            'active_stream_url'      => $toUrl,
            'active_source_index'    => $toIndex,
            'source_check_attempts'  => 0,
            'source_status'          => 'online',
            'source_last_checked_at' => now(),
            'source_last_online_at'  => now(),
            'source_last_error'      => null,
        ]);

        // 3. Restart group reader for remaining channels (now excludes this one).
        if ($leavingMulticast && $toIndex > 0) {
            app(MulticastIngestService::class)->restartGroupExcluding($primaryUrl, $channel->id);
        }

        // 4. Start new ingest on the target URL.
        app(XtreamController::class)->ensureHlsStream(
            (int) $channel->id,
            $toUrl,
            $channel->program_number,
            $channel->local_address
        );
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
     * Probe a URL without updating the channel DB record.
     * Used by the watchdog to test a source URL without side effects.
     * For UDP: checks sibling playlists first (zero cost), then the group
     * reader pid file, so it works even when this channel is the only one
     * on that multicast source.
     */
    public function probeOnly(string $url, Channel $channel): string
    {
        if (str_starts_with($url, 'udp://') || str_starts_with($url, 'rtp://')) {
            // Check any sibling on primary with a fresh playlist
            $siblings = Channel::where('stream_url', $channel->stream_url)
                ->where('id', '!=', $channel->id)
                ->where('active_source_index', 0)
                ->get();
            foreach ($siblings as $sibling) {
                $playlist = storage_path("app/streams/hls/{$sibling->id}/playlist.m3u8");
                if (is_file($playlist) && (time() - @filemtime($playlist)) < 30) {
                    return 'online';
                }
            }

            // Check this channel's own ingest PID file + playlist
            $outputDir = storage_path("app/streams/hls/{$channel->id}");
            $pidFile   = $outputDir . '/ingest.pid';
            $playlist  = is_file($outputDir . '/index.m3u8')
                ? $outputDir . '/index.m3u8'
                : $outputDir . '/playlist.m3u8';

            if (is_file($pidFile)) {
                $pid = (int) trim((string) file_get_contents($pidFile));
                if ($pid > 0 && @file_exists("/proc/{$pid}")) {
                    if (is_file($playlist) && (time() - @filemtime($playlist)) < 30) {
                        return 'online';
                    }
                    $startTime = @filectime("/proc/{$pid}");
                    if ($startTime !== false && (time() - $startTime) < 30) {
                        return 'online';
                    }
                }
            }

            // No healthy sibling — check the group reader pid file directly
            $multicast = app(MulticastIngestService::class);
            $tempChannel = clone $channel;
            $tempChannel->active_stream_url = $url;
            $tempChannel->active_source_index = 0;
            return $multicast->isGroupRunning($tempChannel) ? 'online' : 'offline';
        }

        $result = $this->probeUrl($url, $channel);
        return $result['status'];
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

        // Keep ffprobe's JSON (stdout) separate from warnings/errors (stderr).
        // H.264 sources routinely emit decode warnings to stderr even while
        // healthy; merging them (2>&1) corrupts the JSON so json_decode() fails
        // and healthy HLS sources are falsely reported offline — which would
        // silently break backup failover.
        $jsonFile = tempnam(sys_get_temp_dir(), 'fprobe');
        $errFile  = tempnam(sys_get_temp_dir(), 'fperr');

        $command = sprintf(
            'timeout %d ffprobe -v error -show_streams -show_format -of json -analyzeduration 5M -probesize 5M %s %s > %s 2> %s; echo "EXIT:$?"',
            $timeout,
            $rwTimeout,
            $inputUrl,
            escapeshellarg($jsonFile),
            escapeshellarg($errFile)
        );

        $shellOut = shell_exec($command . ' 2>&1');

        $exitCode = 1;
        if (preg_match('/EXIT:(\d+)\s*$/', $shellOut, $m)) {
            $exitCode = (int) $m[1];
        }

        $data = json_decode((string) file_get_contents($jsonFile), true);
        $errorOutput = (string) file_get_contents($errFile);
        @unlink($jsonFile);
        @unlink($errFile);

        $hasStreamData = is_array($data) && (!empty($data['streams']) || !empty($data['format']));

        if ($exitCode === 0 && $hasStreamData) {
            $details = $this->extractStreamDetails($data);

            return [
                'status' => 'online',
                'message' => 'Source is online and streaming',
                'details' => $details,
            ];
        }

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
            $age = time() - (is_dir($outputDir) ? (int) @filemtime($outputDir) : time());
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
                'details' => ['playlist_age' => null],
            ];
        }

        $mtime = @filemtime($playlist);
        $age = $mtime ? (time() - $mtime) : PHP_INT_MAX;

        // Stale threshold matches XtreamController::INGEST_STALE_SECONDS (90s).
        // A process alive but not writing segments is effectively offline.
        if ($age > 90) {
            return [
                'status' => 'offline',
                'message' => 'Ingest alive but no playlist after ' . $age . 's',
                'details' => ['playlist_age' => $age],
            ];
        }

        if ($age > 30) {
            return [
                'status' => 'offline',
                'message' => 'Playlist stale (' . $age . 's old)',
                'details' => ['playlist_age' => $age],
            ];
        }

        return [
            'status' => 'online',
            'message' => 'Ingest running, segments fresh (' . $age . 's)',
            'details' => ['playlist_age' => $age],
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
