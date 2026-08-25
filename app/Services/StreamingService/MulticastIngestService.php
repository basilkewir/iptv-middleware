<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Manages single-reader ffmpeg processes for UDP multicast groups.
 *
 * Instead of spawning one ffmpeg per channel (each independently joining
 * the multicast group and transcoding audio), this service spawns ONE
 * ffmpeg per multicast source URL that reads all programs at once and
 * outputs separate HLS playlists per channel.
 *
 * This reduces CPU usage by ~85% for multicast channels:
 * - 1 UDP join instead of 11+ per group
 * - Shared demux overhead
 * - Single ffmpeg process management per group
 */
class MulticastIngestService
{
    private const RESTART_BACKOFF_SECONDS = 10;

    // One ffmpeg handles all programs from a multicast group. Threads 0
    // (auto) lets ffmpeg parallelise decode/mux across outputs — with dozens
    // of outputs a fixed low thread count cannot drain the socket in time
    // and the kernel receive queue overflows.
    private const FFMPEG_THREADS = 0;

    // Modest niceness: yields to nginx/PHP/MySQL but must not starve the
    // ingest loop, or the UDP receive queue overflows and packets drop.
    private const NICE_LEVEL = 5;

    // Max 1-min load before the group reader waits before (re)starting.
    private const LOAD_GATE = 16;

    /** Load threshold the group reader waits under before respawning ffmpeg. */
    private const HOLD_GATE = 24;

    /**
     * Get all active multicast channels grouped by their source URL.
     * Returns [sourceUrl => [channelId => Channel, ...], ...]
     */
    public function getChannelGroups(): array
    {
        $channels = Channel::where('is_active', true)
            ->where(function ($q) {
                $q->where('stream_url', 'like', 'udp://%')
                  ->orWhere('stream_url', 'like', 'rtp://%');
            })
            ->get();

        $groups = [];
        foreach ($channels as $channel) {
            $sourceUrl = $this->buildSourceUrl($channel);
            $groups[$sourceUrl][$channel->id] = $channel;
        }

        return $groups;
    }

    /**
     * Build the full input URL for a multicast channel.
     */
    public function buildSourceUrl(Channel $channel): string
    {
        $url = $channel->active_stream_url ?? $channel->stream_url;

        if ($channel->local_address && ! str_contains($url, 'localaddr=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'localaddr=' . $channel->local_address;
        }

        // Large SO_RCVBUF so bursts from the mux don't overflow the kernel
        // receive queue (requires net.core.rmem_max >= 16777216 on the host).
        if (! str_contains($url, 'buffer_size=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'buffer_size=16777216';
        }

        return $url;
    }

    /**
     * Check if a channel's group reader is running and healthy.
     */
    public function isGroupRunning(Channel $channel): bool
    {
        $sourceUrl = $this->buildSourceUrl($channel);
        $pidFile = $this->getGroupPidFile($sourceUrl);

        if (! is_file($pidFile)) {
            return false;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));

        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            @unlink($pidFile);
            return false;
        }

        // Check if the reader is actually producing output for this channel
        $outputDir = storage_path("app/streams/hls/{$channel->id}");
        $playlist = $outputDir . '/playlist.m3u8';

        if (is_file($playlist)) {
            $age = time() - @filemtime($playlist);
            if ($age < 60) {
                return true;
            }
        }

        // Playlist is stale or missing — check if reader process is alive
        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
        if ($cmdline !== false && str_contains($cmdline, 'ffmpeg')) {
            return true;
        }

        return false;
    }

    /**
     * Start or verify the group reader for a channel.
     * Returns true if the reader is running (started or already alive).
     */
    public function ensureGroupReader(Channel $channel): bool
    {
        if ($this->isGroupRunning($channel)) {
            $this->touchHeartbeat($channel);
            return true;
        }

        $sourceUrl = $this->buildSourceUrl($channel);
        $lockKey = "multicast:reader:lock:" . md5($sourceUrl);

        $lock = Cache::lock($lockKey, 30);
        if (! $lock->get()) {
            return false;
        }

        try {
            // Double-check after acquiring lock
            if ($this->isGroupRunning($channel)) {
                $this->touchHeartbeat($channel);
                return true;
            }

            return $this->startGroupReader($channel);
        } finally {
            $lock->release();
        }
    }

    /**
     * Start the single-reader ffmpeg for the multicast group.
     */
    private function startGroupReader(Channel $channel): bool
    {
        $sourceUrl = $this->buildSourceUrl($channel);
        $groups = $this->getChannelGroups();

        if (! isset($groups[$sourceUrl])) {
            return false;
        }

        $groupChannels = $groups[$sourceUrl];
        $scriptDir = storage_path('app');
        $pidFile = $this->getGroupPidFile($sourceUrl);
        $logFile = $this->getGroupLogFile($sourceUrl);
        $groupId = $this->getGroupId($sourceUrl);

        // Clean output directories for all channels in this group
        foreach ($groupChannels as $ch) {
            $dir = storage_path("app/streams/hls/{$ch->id}");
            if (is_dir($dir)) {
                foreach (glob($dir . '/segment_*.ts') ?: [] as $f) {
                    @unlink($f);
                }
                foreach (glob($dir . '/playlist*.m3u8') ?: [] as $f) {
                    @unlink($f);
                }
            } else {
                @mkdir($dir, 0755, true);
            }
            @unlink($dir . '/.stop');
        }

        $cmd = $this->buildGroupReaderCommand($sourceUrl, $groupChannels, $pidFile, $logFile);

        // The group PID file lives in storage/app/multicast — make sure the
        // directory exists or the wrapper's `echo $$ > pidfile` fails silently
        // and every subsequent ensure* call thinks no reader is running.
        @mkdir(dirname($pidFile), 0755, true);

        Log::info('Starting multicast group reader', [
            'source' => $sourceUrl,
            'channels' => array_keys($groupChannels),
            'pid_file' => $pidFile,
        ]);

        $wrapperWithPid = 'echo $$ > ' . escapeshellarg($pidFile) . '; ' . $cmd;
        $shellCmd = 'setsid bash -c ' . escapeshellarg($wrapperWithPid)
            . ' < /dev/null > /dev/null 2>&1 &';

        shell_exec($shellCmd);

        usleep(500000);

        $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;

        if ($pid > 0) {
            cache()->put("multicast:group:" . md5($sourceUrl), $pid, 86400);

            foreach ($groupChannels as $ch) {
                cache()->put("ffmpeg:channel:{$ch->id}", $pid, 86400);
                // Write a per-channel PID file pointing to the group reader
                // so existing monitoring code can check it
                $chPidFile = storage_path("app/streams/hls/{$ch->id}/ingest.pid");
                @file_put_contents($chPidFile, (string) $pid);
            }

            Log::info('Multicast group reader started', [
                'source' => $sourceUrl,
                'pid' => $pid,
                'channel_count' => count($groupChannels),
            ]);

            return true;
        }

        Log::error('Failed to start multicast group reader', [
            'source' => $sourceUrl,
        ]);

        return false;
    }

    /**
     * Build the ffmpeg command for a multi-program group reader.
     * One input, multiple mapped outputs — each producing its own HLS playlist.
     */
    private function buildGroupReaderCommand(
        string $sourceUrl,
        array $channels,
        string $pidFile,
        string $logFile
    ): string {
        $isMulticast = str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://');

        // Build per-channel output segments
        $outputs = [];
        foreach ($channels as $ch) {
            $outputDir = storage_path("app/streams/hls/{$ch->id}");
            $programNumber = $ch->program_number;

            if ($programNumber === null || $programNumber <= 0) {
                continue;
            }

            $outputs[] = sprintf(
                ' -map 0:p:%d -map_chapters -1 -ignore_unknown'
                . '%s'
                . ' -c:a aac -b:a 48k -ac 2 -ar 48000'
                . ' -f hls -hls_time 6 -hls_list_size 5'
                . ' -hls_flags delete_segments+temp_file+independent_segments+append_list'
                . ' -hls_segment_filename %s/segment_%%04d.ts'
                . ' %s/playlist.m3u8',
                $programNumber,
                // Channels flagged for transcoding get a full H.264 re-encode
                // (normalises odd profiles that break TV players); the rest
                // stream-copy at zero CPU cost.
                ((bool) ($ch->transcoding_enabled ?? false))
                    ? ' -c:v libx264 -preset veryfast -crf 26 -tune zerolatency -threads 4'
                    : ' -c:v copy',
                escapeshellarg($outputDir),
                escapeshellarg($outputDir)
            );
        }

        if (empty($outputs)) {
            return 'echo "No valid programs to map"; exit 1';
        }

        $allOutputs = implode(" \\\n", $outputs);

        return sprintf(
            'ODIR=%s; L=%s; '
            . 'echo "GROUP READER START $$ $(date +%%s)" >> "$L"; '
            . 'trap \'echo "GROUP READER EXIT rc=$? $(date +%%s)" >> "$L"\' EXIT; '
            // Wait for load to drop before starting
            . 'LOAD_GATE=' . self::LOAD_GATE . '; '
            . 'for i in $(seq 1 12); do '
            .   'LOAD=$(cut -d. -f1 /proc/loadavg); '
            .   '[ "$LOAD" -lt "$LOAD_GATE" ] && break; '
            .   'echo "LOAD_WAIT load=$LOAD" >> "$L"; sleep 5; '
            . 'done; '
            . 'while true; do '
            .   ('nice -n ' . self::NICE_LEVEL . ' ffmpeg -threads ' . self::FFMPEG_THREADS
            .   ' -fflags +genpts+discardcorrupt -rw_timeout 30000000 -timeout 30000000 -i %s')
            .   " \\\n%s \\\n"
            .   '2>>"$L"; '
            .   'echo "GROUP READER RESTART $(date +%%s)" >> "$L"; '
            .   'sleep 5; '
            .   'LOAD=$(cut -d. -f1 /proc/loadavg); '
            .   'while [ "$LOAD" -ge ' . self::HOLD_GATE . ' ]; do echo "GROUP HOLD load=$LOAD" >> "$L"; sleep 10; LOAD=$(cut -d. -f1 /proc/loadavg); done; '
            . 'done',
            escapeshellarg(storage_path("app/streams/multicast")),
            escapeshellarg($logFile),
            escapeshellarg($sourceUrl),
            $allOutputs
        );
    }

    /**
     * Stop all group readers.
     */
    public function stopAll(): void
    {
        $groups = $this->getChannelGroups();

        foreach ($groups as $sourceUrl => $channels) {
            $pidFile = $this->getGroupPidFile($sourceUrl);

            if (! is_file($pidFile)) {
                continue;
            }

            $pid = (int) trim((string) file_get_contents($pidFile));

            if ($pid > 0) {
                @exec("kill -TERM -{$pid} 2>/dev/null");
                usleep(500000);
                @exec("kill -KILL -{$pid} 2>/dev/null");
            }

            @unlink($pidFile);

            foreach ($channels as $ch) {
                cache()->forget("ffmpeg:channel:{$ch->id}");
            }

            cache()->forget("multicast:group:" . md5($sourceUrl));
        }
    }

    /**
     * Stop a specific group reader.
     */
    public function stopGroup(Channel $channel): void
    {
        $sourceUrl = $this->buildSourceUrl($channel);
        $pidFile = $this->getGroupPidFile($sourceUrl);

        if (! is_file($pidFile)) {
            return;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));

        if ($pid > 0) {
            @exec("kill -TERM -{$pid} 2>/dev/null");
            usleep(500000);
            @exec("kill -KILL -{$pid} 2>/dev/null");
        }

        @unlink($pidFile);

        $groups = $this->getChannelGroups();
        if (isset($groups[$sourceUrl])) {
            foreach ($groups[$sourceUrl] as $ch) {
                cache()->forget("ffmpeg:channel:{$ch->id}");
            }
        }

        cache()->forget("multicast:group:" . md5($sourceUrl));
    }

    /**
     * Get the status of all group readers.
     */
    public function getStatus(): array
    {
        $groups = $this->getChannelGroups();
        $status = [];

        foreach ($groups as $sourceUrl => $channels) {
            $pidFile = $this->getGroupPidFile($sourceUrl);
            $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;
            $alive = $pid > 0 && @file_exists("/proc/{$pid}");

            $channelStatus = [];
            foreach ($channels as $ch) {
                $playlist = storage_path("app/streams/hls/{$ch->id}/playlist.m3u8");
                $playlistAge = is_file($playlist) ? (time() - @filemtime($playlist)) : PHP_INT_MAX;

                $channelStatus[$ch->id] = [
                    'name' => $ch->name,
                    'program' => $ch->program_number,
                    'playlist_age' => $playlistAge,
                    'healthy' => $alive && $playlistAge < 30,
                ];
            }

            $status[$sourceUrl] = [
                'pid' => $pid,
                'alive' => $alive,
                'channel_count' => count($channels),
                'channels' => $channelStatus,
            ];
        }

        return $status;
    }

    private function getGroupPidFile(string $sourceUrl): string
    {
        return storage_path('app/multicast/' . $this->getGroupId($sourceUrl) . '.pid');
    }

    private function getGroupLogFile(string $sourceUrl): string
    {
        return '/tmp/multicast_reader_' . $this->getGroupId($sourceUrl) . '.log';
    }

    private function getGroupId(string $sourceUrl): string
    {
        return md5($sourceUrl);
    }

    private function touchHeartbeat(Channel $channel): void
    {
        $heartbeatFile = storage_path("app/streams/hls/{$channel->id}/.heartbeat");
        @touch($heartbeatFile);
    }
}
