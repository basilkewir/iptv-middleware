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
        $sourceUrl = $this->resolvePushSource($channel);

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
        $isUdpSource = str_starts_with($channel->stream_url ?? '', 'udp://')
            || str_starts_with($channel->stream_url ?? '', 'rtp://');
        $ffmpegCmd = $this->buildFFmpegCommand($sourceUrl, $outputUrl, $destination->protocol, $videoBitrate, $audioBitrate, $isUdpSource);
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

    /**
     * Resolve the best source URL for pushing.
     *
     * For UDP/RTP channels: pull from the local HLS playlist instead of the
     * raw multicast socket. This eliminates the double-ingest race condition
     * where two FFmpeg processes fight over the same multicast socket, causing
     * packet drops, macroblocking, and wasted CPU.
     *
     * For HTTP/HLS/RTMP channels: use the active source URL directly.
     */
    private function resolvePushSource(Channel $channel): string
    {
        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        if (empty($sourceUrl)) {
            return '';
        }

        // For UDP/RTP multicast: check if the local ingest is already running.
        // If so, pull from the local HLS playlist — zero CPU, zero socket conflicts.
        $isMulticast = str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://');
        if ($isMulticast) {
            $playlist = storage_path("app/streams/hls/{$channel->id}/playlist.m3u8");
            if (is_file($playlist)) {
                $age = time() - (int) @filemtime($playlist);
                // Playlist is fresh (< 90s) — local ingest is alive, use it
                if ($age < 90) {
                    // Return the internal HLS URL that Nginx can serve.
                    // The push FFmpeg reads from the local filesystem directly.
                    return $playlist;
                }
            }

            // Local ingest not running — fall back to raw UDP source
            // (ChannelPushService will handle it as a standalone ingest)
            Log::info('Push falling back to raw UDP source (local ingest not running)', [
                'channel_id' => $channel->id,
                'source' => $sourceUrl,
            ]);
        }

        return $sourceUrl;
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
        bool $isLocalHls = false,
    ): string {
        $videoKbps = $videoBitrate ? ($videoBitrate . 'k') : null;
        $audioKbps = $audioBitrate ? ($audioBitrate . 'k') : null;

        $inputOpts = [];
        if ($isLocalHls) {
            // Local HLS playlist from tmpfs — pure stream copy, zero CPU.
            // The segments are already timestamp-corrected by the primary ingest.
            $inputOpts[] = '-fflags +genpts+nobuffer -flags low_delay';
            $inputOpts[] = '-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 2';
        } elseif (str_starts_with($inputUrl, 'http://') || str_starts_with($inputUrl, 'https://')) {
            $inputOpts[] = '-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 -reconnect_on_network_error 1';
        } elseif (str_starts_with($inputUrl, 'rtsp://')) {
            $inputOpts[] = '-rtsp_transport tcp -stimeout 10000000';
        } elseif (str_starts_with($inputUrl, 'udp://') || str_starts_with($inputUrl, 'rtp://')) {
            $inputOpts[] = '-fflags +genpts+discardcorrupt+nobuffer -flags low_delay -err_detect ignore_err -avoid_negative_ts make_zero -timeout 5000000 -rw_timeout 5000000';
        }

        $videoOpts = [];
        if ($isLocalHls) {
            // Local HLS: always copy — segments are already processed
            $videoOpts[] = '-c:v copy';
        } elseif ($videoKbps) {
            $videoOpts[] = '-c:v libx264';
            $videoOpts[] = '-b:v ' . $videoKbps;
            $videoOpts[] = '-preset veryfast';
            $videoOpts[] = '-profile:v main';
            $videoOpts[] = '-pix_fmt yuv420p';
        } else {
            $videoOpts[] = '-c:v copy';
        }

        $audioOpts = [];
        if ($isLocalHls) {
            // Local HLS: always copy audio
            $audioOpts[] = '-c:a copy';
        } elseif ($audioKbps) {
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
        $logFile  = storage_path("logs/push_{$channelId}_{$destinationId}.log");
        $stopFile = $this->getStopFile($channelId, $destinationId);
        $pidFile  = $this->getPidFile($channelId, $destinationId);
        $scriptFile = storage_path("app/push_{$channelId}_{$destinationId}.sh");

        @unlink($stopFile);

        // Write the wrapper as a file — avoids all shell-quoting issues with
        // complex ffmpeg commands that contain single/double quotes.
        $script = <<<BASH
#!/bin/bash
echo \$\$ > {$pidFile}
L={$logFile}
S={$stopFile}
echo "PUSH WRAPPER START channel={$channelId} dest={$destinationId} pid=\$\$ \$(date +%s)" >> "\$L"
trap 'echo "PUSH WRAPPER EXIT \$(date +%s)" >> "\$L"; exit 0' EXIT INT TERM
DELAY=3
while true; do
  [ -f "\$S" ] && echo "STOP FILE FOUND" >> "\$L" && exit 0
  echo "PUSH START \$(date +%s)" >> "\$L"
  {$ffmpegCmd} >> "\$L" 2>&1
  RC=\$?
  echo "PUSH EXIT rc=\$RC \$(date +%s)" >> "\$L"
  [ -f "\$S" ] && echo "STOP FILE FOUND AFTER EXIT" >> "\$L" && exit 0
  if [ \$RC -eq 0 ]; then DELAY=3; else DELAY=\$((DELAY * 2)); [ \$DELAY -gt 30 ] && DELAY=30; fi
  echo "PUSH RESTART delay=\$DELAY" >> "\$L"
  sleep \$DELAY
done
BASH;

        file_put_contents($scriptFile, $script);
        chmod($scriptFile, 0755);

        $shellCmd = 'setsid bash ' . escapeshellarg($scriptFile) . ' < /dev/null > /dev/null 2>&1 &';

        Log::info('Push wrapper starting', [
            'channel_id'  => $channelId,
            'destination_id' => $destinationId,
            'ffmpeg_cmd'  => $ffmpegCmd,
            'script'      => $scriptFile,
        ]);

        exec($shellCmd);

        // Wait up to 5s for PID file
        for ($i = 0; $i < 20; $i++) {
            usleep(250000);
            if (is_file($pidFile)) break;
        }

        if (! is_file($pidFile)) {
            throw new \RuntimeException(
                'Push wrapper failed to start — no PID file after 5s. '
                . 'Check: ' . $logFile
            );
        }

        $pid = (int) trim((string) file_get_contents($pidFile));
        if ($pid <= 0) {
            throw new \RuntimeException("Push wrapper wrote invalid PID: {$pid}");
        }

        Cache::put($this->cacheKey($channelId, $destinationId), $pid, 86400);

        // Give the wrapper up to 3s to stay alive (it should loop forever)
        $alive = false;
        for ($i = 0; $i < 6; $i++) {
            usleep(500000);
            if ($this->processExists($pid)) {
                $alive = true;
                break;
            }
        }

        if (! $alive) {
            $log = @file_get_contents($logFile) ?? 'empty';
            throw new \RuntimeException(
                'Push wrapper exited immediately. Log: ' . substr($log, -800)
            );
        }

        Log::info('Push wrapper started', [
            'channel_id'     => $channelId,
            'destination_id' => $destinationId,
            'wrapper_pid'    => $pid,
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
        if (@file_exists("/proc/{$pid}")) {
            return true;
        }
        exec("kill -0 {$pid} 2>/dev/null", $out, $rc);
        return $rc === 0;
    }
}
