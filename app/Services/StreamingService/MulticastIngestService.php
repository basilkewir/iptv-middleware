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

    // A healthy reader rewrites each channel playlist every ~6s. When a
    // playlist is older than this the bucket is considered frozen even if
    // its process is still alive (mirrors XtreamController::INGEST_STALE_SECONDS).
    private const STALE_SECONDS = 90;

    // Max programs mapped into one shared ffmpeg reader. Buckets bound the
    // blast radius of a single corrupt program killing its whole process.
    private const MAX_OUTPUTS_PER_READER = 5;

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

    // HLS segment duration and playlist size for smooth TV playback.
    // 2s segments with 3 in playlist = 6s window — enough buffer for
    // TV apps to avoid rebuffering while keeping latency low.
    private const HLS_SEGMENT_TIME = 2;
    private const HLS_PLAYLIST_SIZE = 3;

    /**
     * Get all active multicast channels grouped by their source URL.
     * Only includes channels whose ACTIVE source is UDP/RTP — channels that
     * have failed over to an HLS/HTTP backup are excluded so they are served
     * by the per-channel ingest path instead of the shared group reader.
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
            // If the channel has failed over to a non-UDP backup, skip it here —
            // XtreamController::ensureHlsStream() will handle it via the
            // per-channel ingest path.
            $activeUrl = $channel->active_stream_url ?? $channel->stream_url;
            if (! $this->isMulticastUrl($activeUrl)) {
                continue;
            }

            $sourceUrl = $this->buildSourceUrl($channel);
            $groups[$sourceUrl][$channel->id] = $channel;
        }

        return $groups;
    }

    /**
     * Build the full input URL for a multicast channel.
     * Only appends UDP-specific params (localaddr, buffer_size) when the
     * active URL is actually a UDP/RTP source — backup URLs may be HLS/HTTP.
     */
    public function buildSourceUrl(Channel $channel): string
    {
        $url = $channel->active_stream_url ?? $channel->stream_url;

        if (! $this->isMulticastUrl($url)) {
            return $url;
        }

        if ($channel->local_address && ! str_contains($url, 'localaddr=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'localaddr=' . $channel->local_address;
        }

        // Large SO_RCVBUF so bursts from the mux don't overflow the kernel
        // receive queue (requires net.core.rmem_max >= 33554432 on the host).
        if (! str_contains($url, 'buffer_size=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'buffer_size=33554432';
        }

        return $url;
    }

    private function isMulticastUrl(string $url): bool
    {
        return str_starts_with($url, 'udp://') || str_starts_with($url, 'rtp://');
    }

    /**
     * Check if the bucket reader owning this channel is running and healthy.
     * Returns false immediately if the channel has failed over to a non-UDP
     * backup — it is no longer part of any multicast group.
     * A process that is alive but has stopped writing output (zombie ffmpeg)
     * is treated as NOT running so callers will kill and restart it.
     */
    public function isGroupRunning(Channel $channel): bool
    {
        $activeUrl = $channel->active_stream_url ?? $channel->stream_url;
        if (! $this->isMulticastUrl($activeUrl)) {
            return false;
        }
        $bucket = $this->bucketIndexOf($channel);

        if ($bucket === null) {
            return false;
        }

        $pidFile = $this->getGroupPidFile($this->buildSourceUrl($channel), $bucket);

        if (! is_file($pidFile)) {
            return false;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));

        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            @unlink($pidFile);
            return false;
        }

        $outputDir = storage_path("app/streams/hls/{$channel->id}");
        $playlist  = $outputDir . '/playlist.m3u8';

        if (is_file($playlist)) {
            // Stale playlist means the process is frozen — treat as dead.
            return (time() - (int) @filemtime($playlist)) < self::STALE_SECONDS;
        }

        // No playlist yet — only healthy if the process just started.
        $startTime = @filectime("/proc/{$pid}");
        return $startTime !== false && (time() - $startTime) < 30;
    }

    /**
     * Which bucket reader owns this channel (position in the ordered group).
     */
    private function bucketIndexOf(Channel $channel): ?int
    {
        $sourceUrl = $this->buildSourceUrl($channel);
        $groups = $this->getChannelGroups();

        if (! isset($groups[$sourceUrl])) {
            return null;
        }

        $pos = array_search($channel->id, array_keys($groups[$sourceUrl]), true);

        if ($pos === false) {
            return null;
        }

        return intdiv((int) $pos, self::MAX_OUTPUTS_PER_READER);
    }

    /**
     * Start (or verify) the group readers for a channel's multicast source.
     * Outputs are split across multiple reader processes (buckets of
     * self::MAX_OUTPUTS_PER_READER programs): one corrupt program crashing
     * an ffmpeg then only interrupts its bucket — the wrapper respawns it
     * within seconds — instead of freezing every channel at once.
     *
     * Verifies EVERY bucket of the source, not just this channel's own:
     * a healthy bucket must not mask dead sibling buckets.
     */
    public function ensureGroupReader(Channel $channel): bool
    {
        $sourceUrl = $this->buildSourceUrl($channel);
        $groups = $this->getChannelGroups();

        if (! isset($groups[$sourceUrl])) {
            return false;
        }

        if ($this->allBucketsAlive($sourceUrl, count($groups[$sourceUrl]))) {
            if ($this->anyBucketStale($groups[$sourceUrl])) {
                // Processes are alive but their outputs stopped advancing —
                // e.g. one stalled encoder head-of-line blocks every output
                // sharing the same input. Kill the frozen readers so the
                // respawn path below can rebuild them.
                Log::warning('Multicast bucket outputs stale, killing frozen group reader', [
                    'source'   => $sourceUrl,
                    'channels' => count($groups[$sourceUrl]),
                ]);
                $this->killGroupReaders($sourceUrl, count($groups[$sourceUrl]));
            } else {
                $this->touchHeartbeat($channel);
                return true;
            }
        }

        $lockKey = "multicast:reader:lock:" . md5($sourceUrl);

        $lock = Cache::lock($lockKey, 30);
        if (! $lock->get()) {
            return false;
        }

        try {
            // Double-check after acquiring lock
            if ($this->allBucketsAlive($sourceUrl, count($groups[$sourceUrl]))) {
                if ($this->anyBucketStale($groups[$sourceUrl])) {
                    $this->killGroupReaders($sourceUrl, count($groups[$sourceUrl]));
                } else {
                    $this->touchHeartbeat($channel);
                    return true;
                }
            }

            return $this->startGroupReader($channel);
        } finally {
            $lock->release();
        }
    }

    /**
     * True when at least one channel playlist served by this multicast group
     * has not been updated within the staleness window even though the reader
     * process is still running.
     */
    private function anyBucketStale($groupChannels): bool
    {
        $now = time();

        foreach ($groupChannels as $ch) {
            $playlist = storage_path("app/streams/hls/{$ch->id}/playlist.m3u8");

            if (! is_file($playlist)) {
                continue;
            }

            $mtime = @filemtime($playlist);

            if ($mtime !== false && ($now - $mtime) > self::STALE_SECONDS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Terminate every bucket reader process for a multicast group and clear
     * its pid files so startGroupReader() sees them as dead.
     */
    private function killGroupReaders(string $sourceUrl, int $channelCount): void
    {
        $buckets = (int) ceil($channelCount / self::MAX_OUTPUTS_PER_READER);

        for ($i = 0; $i < $buckets; $i++) {
            $pidFile = $this->getGroupPidFile($sourceUrl, $i);

            if (! is_file($pidFile)) {
                continue;
            }

            $pid = (int) trim((string) @file_get_contents($pidFile));

            if ($pid > 0) {
                // Kill the whole process group (setsid leader + ffmpeg child).
                @exec("kill -TERM -{$pid} 2>/dev/null");
                @exec("kill -TERM {$pid} 2>/dev/null");
            }

            @unlink($pidFile);
        }

        // Give the kernel a moment to reap the processes before respawn.
        usleep(500000);
    }

    /**
     * True when every expected bucket reader process is alive.
     */
    private function allBucketsAlive(string $sourceUrl, int $channelCount): bool
    {
        $buckets = (int) ceil($channelCount / self::MAX_OUTPUTS_PER_READER);

        for ($i = 0; $i < $buckets; $i++) {
            $pidFile = $this->getGroupPidFile($sourceUrl, $i);

            if (! is_file($pidFile)) {
                return false;
            }

            $pid = (int) trim((string) @file_get_contents($pidFile));

            if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
                @unlink($pidFile);
                return false;
            }

            $cmdline = @file_get_contents("/proc/{$pid}/cmdline");

            if ($cmdline === false || ! str_contains($cmdline, 'ffmpeg')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Start the group reader ffmpeg process(es) for the multicast group.
     * Channels are split into buckets of MAX_OUTPUTS_PER_READER programs:
     * one corrupt program crashing an ffmpeg then only interrupts its own
     * bucket (respawned within seconds) instead of freezing every channel.
     */
    private function startGroupReader(Channel $channel): bool
    {
        $sourceUrl = $this->buildSourceUrl($channel);
        $groups = $this->getChannelGroups();

        if (! isset($groups[$sourceUrl])) {
            return false;
        }

        $groupChannels = $groups[$sourceUrl];

        // Split into buckets of MAX_OUTPUTS_PER_READER programs each.
        // Only dead buckets get their output wiped and respawned — healthy
        // buckets are left completely untouched to avoid disrupting viewers.
        $buckets = array_chunk($groupChannels, self::MAX_OUTPUTS_PER_READER, true);

        $started = 0;

        foreach ($buckets as $index => $bucketChannels) {
            $pidFile = $this->getGroupPidFile($sourceUrl, $index);
            $logFile = $this->getGroupLogFile($sourceUrl, $index);

            if (is_file($pidFile)) {
                $existingPid = (int) trim((string) @file_get_contents($pidFile));

                if ($existingPid > 0 && @file_exists("/proc/{$existingPid}")) {
                    continue; // this bucket is still healthy — leave it alone
                }

                @unlink($pidFile);
            }

            // Only wipe output for this dead bucket's channels
            foreach ($bucketChannels as $ch) {
                $dir = storage_path("app/streams/hls/{$ch->id}");
                if (is_dir($dir)) {
                    foreach (glob($dir . '/segment_*.ts') ?: [] as $f) { @unlink($f); }
                    foreach (glob($dir . '/playlist*.m3u8') ?: [] as $f) { @unlink($f); }
                } else {
                    @mkdir($dir, 0755, true);
                }
                @unlink($dir . '/.stop');
            }

            $cmd = $this->buildGroupReaderCommand($sourceUrl, $bucketChannels, $pidFile, $logFile);

            // The group PID file lives in storage/app/multicast — make sure the
            // directory exists or the wrapper's `echo $$ > pidfile` fails silently
            // and every subsequent ensure* call thinks no reader is running.
            @mkdir(dirname($pidFile), 0755, true);

            Log::info('Starting multicast group reader', [
                'source' => $sourceUrl,
                'bucket' => $index,
                'channels' => array_keys($bucketChannels),
                'pid_file' => $pidFile,
            ]);

            $wrapperWithPid = 'echo $$ > ' . escapeshellarg($pidFile) . '; ' . $cmd;
            $shellCmd = 'setsid bash -c ' . escapeshellarg($wrapperWithPid)
                . ' < /dev/null > /dev/null 2>&1 &';

            shell_exec($shellCmd);

            usleep(500000);

            $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;

            if ($pid > 0) {
                cache()->put("multicast:group:" . md5($sourceUrl) . ":{$index}", $pid, 86400);

                foreach ($bucketChannels as $ch) {
                    cache()->put("ffmpeg:channel:{$ch->id}", $pid, 86400);
                    // Write a per-channel PID file pointing to the bucket
                    // reader so existing monitoring code can check it
                    $chPidFile = storage_path("app/streams/hls/{$ch->id}/ingest.pid");
                    @file_put_contents($chPidFile, (string) $pid);
                }

                Log::info('Multicast group reader started', [
                    'source' => $sourceUrl,
                    'bucket' => $index,
                    'pid' => $pid,
                    'channel_count' => count($bucketChannels),
                ]);

                $started++;
            } else {
                Log::error('Failed to start multicast group reader', [
                    'source' => $sourceUrl,
                    'bucket' => $index,
                ]);
            }
        }

        return $started > 0;
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

        // Wait for the local multicast interface to hold its address before
        // joining the group — otherwise ffmpeg fails with
        // "setsockopt(IP_ADD_MEMBERSHIP): No such device" and the bucket
        // crash-loops until the wrapper gives up.
        $first = reset($channels);
        $localAddress = is_object($first) ? ($first->local_address ?? null) : null;

        $networkWait = '';
        if ($isMulticast && $localAddress !== null && $localAddress !== '') {
            $networkWait = 'for i in $(seq 1 30); do '
                . 'if ip addr show | grep -q ' . escapeshellarg($localAddress) . '; then '
                . '  echo "NETWORK READY $i" >> "$L"; break; fi; '
                . 'echo "WAITING FOR NETWORK $i" >> "$L"; sleep 1; done; ';
        }

        // Build per-channel output segments
        $outputs = [];
        foreach ($channels as $ch) {
            $outputDir = storage_path("app/streams/hls/{$ch->id}");
            $programNumber = $ch->program_number;

            if ($programNumber === null || $programNumber <= 0) {
                continue;
            }

            // Channels flagged for transcoding get a full H.264 + AAC re-encode
            // (normalises odd profiles/codecs that break TV players). The rest
            // stream-copy BOTH video and audio at near-zero CPU cost — this is
            // what keeps dozens of UDP channels almost free, like Flussonic.
            // However, ALL channels get audio normalized to AAC for TV app
            // compatibility — only video stays copy when transcoding is off.
            $transcoding = (bool) ($ch->transcoding_enabled ?? false);

            $videoCodec = $transcoding
                ? ($this->resolveEncoder($ch) === 'gpu'
                    ? ' -c:v h264_nvenc -preset p4 -tune ll -rc vbr -cq 28 -b:v 0 -maxrate 4000k -bufsize 8000k'
                    : ' -c:v libx264 -preset veryfast -crf 26 -tune zerolatency -threads 4')
                : ' -c:v copy';

            // Always normalize audio to AAC for TV app compatibility — raw
            // AC3/MP2/DTS may not decode on many TV players. The encode cost
            // is negligible compared to the viewer experience improvement.
            $audioCodec = ' -c:a aac -b:a 128k -ac 2 -ar 48000';

            $outputs[] = sprintf(
                ' -map 0:p:%d -map_chapters -1 -ignore_unknown'
                . '%s'
                . ' -max_muxing_queue_size 65536'
                . '%s'
            .   ' -f hls -hls_time %d -hls_list_size %d'
            .   ' -hls_flags delete_segments+temp_file+independent_segments+append_list'
            .   ' -muxdelay 0 -muxpreload 0'
            .   ' -hls_segment_filename %s/segment_%%04d.ts'
                . ' %s/playlist.m3u8',
                $programNumber,
                $videoCodec,
                $audioCodec,
                self::HLS_SEGMENT_TIME,
                self::HLS_PLAYLIST_SIZE,
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
            . $networkWait
            // Wait for load to drop before starting
            . 'LOAD_GATE=' . self::LOAD_GATE . '; '
            . 'for i in $(seq 1 12); do '
            .   'LOAD=$(cut -d. -f1 /proc/loadavg); '
            .   '[ "$LOAD" -lt "$LOAD_GATE" ] && break; '
            .   'echo "LOAD_WAIT load=$LOAD" >> "$L"; sleep 5; '
            . 'done; '
. 'while true; do '
            .   ('nice -n ' . self::NICE_LEVEL . ' ffmpeg -threads ' . self::FFMPEG_THREADS
            .   ' -fflags +genpts+discardcorrupt+igndts+nobuffer -flags low_delay -err_detect ignore_err -avoid_negative_ts make_zero -max_interleave_delta 0 -flush_packets 1 -probesize 1M -analyzeduration 500000 -rw_timeout 5000000 -timeout 5000000 -i %s')
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
     * Stop all bucket readers for a multicast group identified by the raw
     * primary stream_url (before localaddr/buffer_size params are appended).
     * Used by doSwitch() which only has the channel's stream_url, not the
     * full built source URL that the pid files are keyed on.
     */
    public function stopGroupByPrimaryUrl(string $primaryUrl): void
    {
        // Find a channel still on primary to reconstruct the full source URL
        // (which includes localaddr= and buffer_size= params used for the md5).
        $channel = Channel::where('stream_url', $primaryUrl)
            ->where('is_active', true)
            ->where('active_source_index', 0)
            ->first();

        if ($channel) {
            $this->stopGroup($channel);
            return;
        }

        // Fallback: no channel on primary anymore — try raw URL hash.
        $groupId = $this->getGroupId($primaryUrl);
        $pattern = storage_path("app/multicast/{$groupId}_*.pid");

        foreach (glob($pattern) ?: [] as $pidFile) {
            $pid = (int) trim((string) @file_get_contents($pidFile));
            if ($pid > 0) {
                @exec("kill -TERM -{$pid} 2>/dev/null");
                @exec("kill -TERM {$pid} 2>/dev/null");
            }
            @unlink($pidFile);
        }

        usleep(500000);

        foreach (glob($pattern) ?: [] as $pidFile) {
            $pid = (int) trim((string) @file_get_contents($pidFile));
            if ($pid > 0) {
                @exec("kill -KILL -{$pid} 2>/dev/null");
            }
            @unlink($pidFile);
        }

        cache()->forget("multicast:group:" . md5($primaryUrl));
    }

    /**
     * Restart the group reader for a multicast source, excluding one channel
     * that has failed over to a backup. This rebuilds the ffmpeg command
     * without the excluded channel's -map output.
     */
    public function restartGroupExcluding(string $primaryUrl, int $excludeChannelId): void
    {
        // Build a temporary channel to locate the group
        $remaining = Channel::where('is_active', true)
            ->where(function ($q) use ($primaryUrl) {
                $q->where('stream_url', $primaryUrl);
            })
            ->where('id', '!=', $excludeChannelId)
            ->where('active_source_index', 0)
            ->get();

        if ($remaining->isEmpty()) {
            return; // No other channels on this source — nothing to restart
        }

        // Use the first remaining channel to drive ensureGroupReader
        $this->ensureGroupReader($remaining->first());
    }

    /**
     * Stop all group readers.
     */
    public function stopAll(): void
    {
        foreach (glob(storage_path('app/multicast') . '/*.pid') ?: [] as $pidFile) {
            $pid = (int) trim((string) @file_get_contents($pidFile));

            if ($pid > 0) {
                @exec("kill -TERM -{$pid} 2>/dev/null");
                usleep(500000);
                @exec("kill -KILL -{$pid} 2>/dev/null");
            }

            @unlink($pidFile);
        }

        foreach ($this->getChannelGroups() as $sourceUrl => $channels) {
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

        foreach (glob(storage_path('app/multicast/' . $this->getGroupId($sourceUrl) . '_*.pid')) ?: [] as $pidFile) {
            $pid = (int) trim((string) @file_get_contents($pidFile));

            if ($pid > 0) {
                @exec("kill -TERM -{$pid} 2>/dev/null");
                usleep(500000);
                @exec("kill -KILL -{$pid} 2>/dev/null");
            }

            @unlink($pidFile);
        }

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

    private function getGroupPidFile(string $sourceUrl, int $bucket = 0): string
    {
        return storage_path('app/multicast/' . $this->getGroupId($sourceUrl) . "_{$bucket}.pid");
    }

    private function getGroupLogFile(string $sourceUrl, int $bucket = 0): string
    {
        return '/tmp/multicast_reader_' . $this->getGroupId($sourceUrl) . "_{$bucket}.log";
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

    private function resolveEncoder(Channel $channel): string
    {
        $device = strtolower($channel->transcoding_device ?? 'cpu');
        if ($device === 'gpu' && $this->hasNvenc()) {
            return 'gpu';
        }
        return 'cpu';
    }

    private function hasNvenc(): bool
    {
        static $cached;
        if ($cached === null) {
            exec('ffmpeg -hide_banner -encoders 2>/dev/null', $output, $exit);
            $cached = $exit === 0 && implode("\n", $output) !== '' && str_contains(implode("\n", $output), 'h264_nvenc');
        }
        return $cached;
    }
}
