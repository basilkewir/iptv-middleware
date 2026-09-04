<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Models\Channel;
use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChannelPushService
{
    private string $ffmpegPath;

    public function __construct()
    {
        $this->ffmpegPath = config('streaming.transcoding.ffmpeg_path', '/usr/bin/ffmpeg');
    }

    public function startPush(
        Channel $channel,
        PushDestination $destination,
        ?string $streamKey = null,
        ?int $videoBitrate = null,
        ?int $audioBitrate = null,
    ): ChannelPushDestination {
        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        if (empty($sourceUrl)) {
            throw new \RuntimeException('Channel has no active source URL.');
        }

        $existing = ChannelPushDestination::where('channel_id', $channel->id)
            ->where('push_destination_id', $destination->id)
            ->first();

        if ($existing && $existing->isPushing() && $this->isWrapperAlive($existing->ffmpeg_pid)) {
            return $existing;
        }

        // Kill stale wrapper if it exists but is dead
        if ($existing && $existing->ffmpeg_pid) {
            $this->killProcessGroup($existing->ffmpeg_pid);
        }

        $outputUrl = $this->buildOutputUrl($destination, $streamKey);
        $ffmpegCmd = $this->buildFFmpegCommand($sourceUrl, $outputUrl, $destination->protocol, $videoBitrate, $audioBitrate);
        $pid = $this->executePushWrapper($ffmpegCmd, $channel->id, $destination->id);

        if ($existing) {
            $existing->update([
                'stream_key' => $streamKey,
                'video_bitrate' => $videoBitrate,
                'audio_bitrate' => $audioBitrate,
                'status' => 'pushing',
                'ffmpeg_pid' => $pid,
                'started_at' => now(),
                'stopped_at' => null,
                'last_error' => null,
                'restart_count' => 0,
                'last_restart_at' => null,
            ]);
            $record = $existing->fresh();
        } else {
            $record = ChannelPushDestination::create([
                'channel_id' => $channel->id,
                'push_destination_id' => $destination->id,
                'stream_key' => $streamKey,
                'video_bitrate' => $videoBitrate,
                'audio_bitrate' => $audioBitrate,
                'status' => 'pushing',
                'ffmpeg_pid' => $pid,
                'started_at' => now(),
                'restart_count' => 0,
            ]);
        }

        Log::info('Channel push started', [
            'channel_id' => $channel->id,
            'destination_id' => $destination->id,
            'source' => $sourceUrl,
            'output' => $outputUrl,
            'stream_key' => $streamKey,
            'video_bitrate' => $videoBitrate,
            'audio_bitrate' => $audioBitrate,
            'pid' => $pid,
        ]);

        return $record;
    }

    public function stopPush(ChannelPushDestination $push): void
    {
        if (! $push->isPushing()) {
            return;
        }

        $pid = $push->ffmpeg_pid;
        if ($pid) {
            // Kill the entire process group (setsid wrapper + ffmpeg child)
            $this->killProcessGroup($pid);

            // Also write a .stop file so the wrapper loop exits cleanly
            $stopFile = $this->getStopFile($push->channel_id, $push->push_destination_id);
            @file_put_contents($stopFile, '1');

            Cache::forget($this->cacheKey($push->channel_id, $push->push_destination_id));
            Log::info('Channel push stopped', ['push_id' => $push->id, 'pid' => $pid]);
        }

        $push->update([
            'status' => 'idle',
            'ffmpeg_pid' => null,
            'stopped_at' => now(),
            'restart_count' => 0,
            'last_restart_at' => null,
        ]);
    }

    public function stopAllPushes(): void
    {
        ChannelPushDestination::where('status', 'pushing')->get()->each(
            fn (ChannelPushDestination $push) => $this->stopPush($push)
        );
    }

    public function isPushing(int $channelId, int $destinationId): bool
    {
        $push = ChannelPushDestination::where('channel_id', $channelId)
            ->where('push_destination_id', $destinationId)
            ->first();

        if (! $push || ! $push->isPushing()) {
            return false;
        }

        return $this->isWrapperAlive($push->ffmpeg_pid);
    }

    public function getActivePushes(): array
    {
        return ChannelPushDestination::with(['channel', 'pushDestination'])
            ->where('status', 'pushing')
            ->get()
            ->map(function (ChannelPushDestination $push) {
                $alive = $this->isWrapperAlive($push->ffmpeg_pid);

                return [
                    'id' => $push->id,
                    'channel_id' => $push->channel_id,
                    'channel' => $push->channel->name ?? 'Unknown',
                    'destination' => $push->pushDestination->name ?? 'Unknown',
                    'protocol' => $push->pushDestination->protocol ?? 'unknown',
                    'stream_key' => $push->stream_key,
                    'video_bitrate' => $push->video_bitrate,
                    'audio_bitrate' => $push->audio_bitrate,
                    'started_at' => $push->started_at?->toISOString(),
                    'pid' => $push->ffmpeg_pid,
                    'alive' => $alive,
                ];
            })
            ->toArray();
    }

    public function buildOutputUrl(PushDestination $destination, ?string $streamKey = null): string
    {
        $base = rtrim($destination->url, '/');

        if (! empty($streamKey)) {
            $base .= '/' . ltrim($streamKey, '/');
        } elseif (! empty($destination->stream_key)) {
            $base .= '/' . ltrim($destination->stream_key, '/');
        }

        if (! empty($destination->username) && ! empty($destination->password) && $destination->protocol === 'rtmp') {
            $parsed = parse_url($base);
            if ($parsed !== false) {
                $auth = rawurlencode($destination->username) . ':' . rawurlencode($destination->password);
                $host = $parsed['host'] ?? '';
                $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                $path = $parsed['path'] ?? '';
                $base = ($parsed['scheme'] ?? 'rtmp') . '://' . $auth . '@' . $host . $port . $path;
            }
        }

        if (! empty($destination->password) && $destination->protocol === 'srt') {
            $separator = str_contains($base, '?') ? '&' : '?';
            $base .= $separator . 'passphrase=' . rawurlencode($destination->password);
        }

        return $base;
    }

    public function buildFFmpegCommand(
        string $inputUrl,
        string $outputUrl,
        string $protocol,
        ?int $videoBitrate = null,
        ?int $audioBitrate = null,
    ): string {
        $videoKbps = $videoBitrate ? ($videoBitrate . 'k') : null;
        $audioKbps = $audioBitrate ? ($audioBitrate . 'k') : null;

        $inputOpts = [];
        if (str_starts_with($inputUrl, 'http://') || str_starts_with($inputUrl, 'https://')) {
            $inputOpts[] = '-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 -reconnect_on_network_error 1';
        } elseif (str_starts_with($inputUrl, 'rtsp://')) {
            $inputOpts[] = '-rtsp_transport tcp -stimeout 10000000';
        } elseif (str_starts_with($inputUrl, 'udp://') || str_starts_with($inputUrl, 'rtp://')) {
            $inputOpts[] = '-timeout 5000000 -rw_timeout 5000000';
        }

        $videoOpts = [];
        if ($videoKbps) {
            $videoOpts[] = '-c:v libx264';
            $videoOpts[] = '-b:v ' . $videoKbps;
            $videoOpts[] = '-preset veryfast';
            $videoOpts[] = '-profile:v main';
            $videoOpts[] = '-pix_fmt yuv420p';
        } else {
            $videoOpts[] = '-c:v copy';
        }

        $audioOpts = [];
        if ($audioKbps) {
            $audioOpts[] = '-c:a aac';
            $audioOpts[] = '-b:a ' . $audioKbps;
            $audioOpts[] = '-ac 2';
        } else {
            $audioOpts[] = '-c:a aac';
            $audioOpts[] = '-b:a 128k';
            $audioOpts[] = '-ac 2';
        }

        $outputOpts = ['-flush_packets 1', '-max_muxing_queue_size 1024'];
        $format = $protocol === 'srt' ? 'mpegts' : 'flv';

        $parts = array_merge(
            [$this->ffmpegPath],
            $inputOpts,
            ['-i ' . escapeshellarg($inputUrl)],
            $videoOpts,
            $audioOpts,
            $outputOpts,
            ['-f ' . $format],
            [escapeshellarg($outputUrl)],
        );

        return implode(' ', $parts);
    }

    /**
     * Execute FFmpeg inside a bash wrapper that auto-restarts on failure.
     * The wrapper loop: while true; do ffmpeg ...; check .stop file; sleep 5; done
     * This makes the push permanent — it survives source drops, network hiccups,
     * and FFmpeg crashes.
     */
    private function executePushWrapper(string $ffmpegCmd, int $channelId, int $destinationId): int
    {
        $logFile = storage_path("logs/push_{$channelId}_{$destinationId}.log");
        $stopFile = $this->getStopFile($channelId, $destinationId);
        $pidFile = $this->getPidFile($channelId, $destinationId);

        // Remove stale .stop file
        @unlink($stopFile);

        // Build the wrapper script
        $wrapper = 'echo $$ > ' . escapeshellarg($pidFile) . '; '
            . 'L=' . escapeshellarg($logFile) . '; '
            . 'S=' . escapeshellarg($stopFile) . '; '
            . 'echo "PUSH WRAPPER START channel=' . $channelId . ' dest=' . $destinationId . ' pid=$$ $(date +%%s)" >> "$L"; '
            . 'trap \'echo "PUSH WRAPPER EXIT rc=$? $(date +%%s)" >> "$L"; rm -f "$S"; exit 0\' EXIT INT TERM; '
            . 'DELAY=3; '
            . 'while true; do '
            .   '[ -f "$S" ] && echo "STOP FILE FOUND" >> "$L" && exit 0; '
            .   'echo "PUSH START $(date +%%s)" >> "$L"; '
            .   $ffmpegCmd . ' >> "$L" 2>&1; '
            .   'RC=$?; '
            .   'echo "PUSH EXIT rc=$RC $(date +%%s)" >> "$L"; '
            .   '[ -f "$S" ] && echo "STOP FILE FOUND AFTER EXIT" >> "$L" && exit 0; '
            .   'if [ $RC -eq 0 ]; then DELAY=3; else DELAY=$((DELAY * 2)); [ $DELAY -gt 30 ] && DELAY=30; fi; '
            .   'echo "PUSH RESTART delay=$DELAY" >> "$L"; '
            .   'sleep $DELAY; '
            . 'done';

        $shellCmd = 'setsid bash -c ' . escapeshellarg($wrapper) . ' < /dev/null > /dev/null 2>&1 &';

        Log::info('Push wrapper starting', [
            'channel_id' => $channelId,
            'destination_id' => $destinationId,
            'ffmpeg_cmd' => $ffmpegCmd,
        ]);

        exec($shellCmd);

        // Wait for PID file to appear
        for ($i = 0; $i < 20; $i++) {
            usleep(250000); // 250ms
            if (is_file($pidFile)) {
                break;
            }
        }

        if (! is_file($pidFile)) {
            throw new \RuntimeException("Push wrapper failed to start — no PID file after 5s.");
        }

        $pid = (int) trim((string) file_get_contents($pidFile));

        if ($pid <= 0) {
            throw new \RuntimeException("Push wrapper wrote invalid PID: {$pid}");
        }

        Cache::put($this->cacheKey($channelId, $destinationId), $pid, 86400);

        // Verify the wrapper process is alive
        usleep(500000);
        if (! $this->processExists($pid)) {
            $log = @file_get_contents($logFile);
            throw new \RuntimeException("Push wrapper exited immediately. PID={$pid}. Log: " . substr($log ?? 'empty', 0, 1000));
        }

        Log::info('Push wrapper started', [
            'channel_id' => $channelId,
            'destination_id' => $destinationId,
            'wrapper_pid' => $pid,
            'pid_file' => $pidFile,
            'log_file' => $logFile,
        ]);

        return $pid;
    }

    public function isWrapperAlive(?int $pid): bool
    {
        if (! $pid || $pid <= 0) {
            return false;
        }

        return $this->processExists($pid);
    }

    protected function killProcessGroup(int $pid): void
    {
        // Kill the whole process group (setsid leader + ffmpeg child)
        @exec("kill -TERM -{$pid} 2>/dev/null");
        @exec("kill -TERM {$pid} 2>/dev/null");
        usleep(500000);
        // Force kill if still alive
        if ($this->processExists($pid)) {
            @exec("kill -KILL -{$pid} 2>/dev/null");
            @exec("kill -KILL {$pid} 2>/dev/null");
        }
    }

    private function getStopFile(int $channelId, int $destinationId): string
    {
        return storage_path("app/push_{$channelId}_{$destinationId}.stop");
    }

    private function getPidFile(int $channelId, int $destinationId): string
    {
        return storage_path("app/push_{$channelId}_{$destinationId}.pid");
    }

    private function cacheKey(int $channelId, int $destinationId): string
    {
        return "push:ffmpeg:{$channelId}:{$destinationId}";
    }

    private function processExists(int $pid): bool
    {
        return @file_exists("/proc/{$pid}") || exec("kill -0 {$pid} 2>/dev/null") === '';
    }
}
