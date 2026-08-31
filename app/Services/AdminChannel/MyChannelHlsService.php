<?php

declare(strict_types=1);

namespace App\Services\AdminChannel;

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelBroadcast;
use App\Models\AdminChannel\MyChannelContent;
use App\Models\AdminChannel\MyChannelPlaylist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 24/7 "Studio Playout" engine for admin channels.
 *
 * Architecture
 * ------------
 * 1. PREPARE  — every playlist item is transcoded once into a canonical
 *    intermediate (uniform H.264/AAC-48k, constant frame rate, baked-in
 *    start/end/custom offsets, guaranteed audio track). Mixed codecs / frame
 *    rates / portrait-phone clips can no longer break the playout.
 * 2. PLAYOUT  — a single long-lived ffmpeg reads the prepared files through
 *    the concat demuxer and re-encodes to HLS. Output timestamps are forced to
 *    a constant frame timeline (fps + setpts=N/(fps*TB)) so clip boundaries
 *    are timestamp-continuous; this eliminates the NVENC ">1000 frames
 *    duplicated" blow-ups and the perceived end-of-video cut.
 * 3. WRAPPER  — a `while true` shell loop relaunches ffmpeg, computing the
 *    next HLS segment number from the segments already on disk so the m3u8
 *    stays continuous across any crash/restart.
 * 4. WATCHDOG — process death *and* freeze detection (stream stopped advancing)
 *    both trigger a restart; a broadcast only ends when the admin stops it.
 */
class MyChannelHlsService
{
    private string $segmentRoot;
    private string $normalizedRoot;
    private string $ffmpeg;
    private string $ffprobe;
    private int $segmentDuration = 6;
    private int $playlistSize    = 12;

    public function __construct()
    {
        $this->segmentRoot    = storage_path('app/streams/hls');
        $this->normalizedRoot = storage_path('app/streams/normalized');
        $this->ffmpeg         = config('streaming.transcoding.ffmpeg_path', '/usr/bin/ffmpeg');
        $this->ffprobe        = config('streaming.transcoding.ffprobe_path', '/usr/bin/ffprobe');
    }

    public function start(MyChannelBroadcast $broadcast): bool
    {
        $channel = $broadcast->channel;

        if (! $channel) {
            return false;
        }

        $streamDir = $this->streamDir($channel);
        $wasRunning = $this->isRunning($channel);

        if ($wasRunning) {
            // Kill existing process but keep segments on disk
            $this->softStop($channel);
        } else {
            $this->stop($channel);
        }

        $this->ensureDirectory($streamDir);

        // Make sure every prepared intermediate used by the playout is fresh.
        // Repeated starts are cheap: a signature file skips up-to-date files.
        $prepStart = microtime(true);
        try {
            $prep = $this->prepareChannel($channel);
        } catch (\Throwable $e) {
            Log::error('My channel prepare failed', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            $prep = ['prepared' => 0, 'skipped' => 0, 'failed' => []];
        }
        if ($prep['prepared'] > 0 || $prep['failed']) {
            Log::info('My channel prepare summary', [
                'channel_id' => $channel->id,
                'prepared'   => $prep['prepared'],
                'skipped'    => $prep['skipped'],
                'failed'     => $prep['failed'],
                'seconds'    => round(microtime(true) - $prepStart, 2),
            ]);
        }

        $playlist = $this->resolvePlaylist($channel);

        if ($playlist->isEmpty()) {
            $broadcast->update(['status' => 'error', 'error_message' => 'No playable content in playlist']);
            Log::warning('My channel broadcast started with empty playlist', ['channel_id' => $channel->id]);
            return false;
        }

        try {
            $files = $this->collectFiles($playlist, $channel);
            if (empty($files)) {
                throw new \RuntimeException('No playable media files found on disk');
            }

            $this->writeOverlayAssets($streamDir, $channel);

            // When restarting, keep existing segment numbering for seamless HLS
            $startSegment = 0;
            if ($wasRunning || is_dir($streamDir)) {
                $segments    = glob("{$streamDir}/segment_*.ts") ?: [];
                if (! empty($segments)) {
                    natsort($segments);
                    $last        = basename((string) end($segments), '.ts');
                    $startSegment = (int) substr($last, 8) + 1;
                }
            }

            $loopScript = $this->writeLoopScript($streamDir, $files, $channel, $startSegment);
        } catch (\Throwable $e) {
            $broadcast->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            Log::error('Failed to build loop script', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            return false;
        }

        $pid = $this->launchScript($loopScript, $streamDir);

        if (! $pid) {
            $broadcast->update(['status' => 'error', 'error_message' => 'Failed to launch playout script']);
            return false;
        }

        Cache::put($this->cacheKey($channel), $pid, 86400);

        $broadcast->update(['status' => 'running', 'start_time' => now(), 'error_message' => null]);
        $channel->update(['broadcast_status' => 'live']);

        Log::info('My channel HLS playout started', [
            'channel_id' => $channel->id,
            'pid'        => $pid,
            'output'     => $streamDir,
            'sources'    => count($files),
        ]);

        return true;
    }

    public function stop(AdminChannel $channel): void
    {
        $pid = (int) Cache::get($this->cacheKey($channel));
        $streamDir = $this->streamDir($channel);

        $this->killPlayout($pid, $streamDir);

        Cache::forget($this->cacheKey($channel));
        Log::info('Playout terminated', ['channel_id' => $channel->id, 'pid' => $pid]);

        if (File::isDirectory($streamDir)) {
            File::deleteDirectory($streamDir);
        }
    }

    public function softStop(AdminChannel $channel): void
    {
        $pid = (int) Cache::get($this->cacheKey($channel));
        $streamDir = $this->streamDir($channel);

        $this->killPlayout($pid, $streamDir);

        Cache::forget($this->cacheKey($channel));
        Log::info('Playout soft-stopped', ['channel_id' => $channel->id, 'pid' => $pid]);
    }

    /**
     * Kill the playout wrapper and any ffmpeg still writing to the stream dir.
     *
     * NVENC ffmpeg sometimes wedges while handling SIGTERM (stuck in a futex
     * wait), which left orphan ffmpeg processes running after "End Broadcast".
     * So we send SIGTERM to the process group and matching PIDs, give them a
     * short grace period, then escalate to SIGKILL for anything still alive.
     */
    private function killPlayout(?int $pid, string $streamDir): void
    {
        $pattern = escapeshellarg($streamDir);

        if ($pid) {
            @exec("kill -TERM -{$pid} 2>/dev/null");
            @exec("kill -TERM {$pid} 2>/dev/null");
        }
        @exec("pkill -TERM -f {$pattern} 2>/dev/null");

        usleep(1500000); // grace period for graceful shutdown

        if ($pid) {
            @exec("kill -9 -{$pid} 2>/dev/null");
            @exec("kill -9 {$pid} 2>/dev/null");
        }
        @exec("pkill -9 -f {$pattern} 2>/dev/null");

        usleep(300000); // let SIGKILL land before the caller deletes the dir
    }

    public function isRunning(AdminChannel $channel): bool
    {
        $streamDir = $this->streamDir($channel);
        $slug      = basename($streamDir);

        // Candidate PIDs: cache first, then the on-disk pid file written at
        // launch. The pid file keeps liveness detection working even when the
        // cache store does not persist across processes (e.g. array driver)
        // or was flushed.
        $pids = [];

        $cached = Cache::get($this->cacheKey($channel));
        if ($cached) {
            $pids[] = (int) $cached;
        }

        $pidFile = "{$streamDir}/playout.pid";
        if (is_file($pidFile)) {
            $fromFile = (int) trim((string) @file_get_contents($pidFile));
            if ($fromFile > 0) {
                $pids[] = $fromFile;
            }
        }

        foreach (array_unique(array_filter($pids)) as $pid) {
            if (! @file_exists("/proc/{$pid}")) {
                continue;
            }

            $cmdline = @file_get_contents("/proc/{$pid}/cmdline");

            if ($cmdline === false) {
                continue;
            }

            // The wrapper loop script contains the stream dir path.
            // An ffmpeg child contains the slug in its output path.
            // Either counts as "running".
            if (str_contains($cmdline, $slug)) {
                return true;
            }
        }

        // Nothing alive — drop the stale pid file so it can't linger forever.
        if (is_file($pidFile)) {
            @unlink($pidFile);
        }

        return false;
    }

    /**
     * True when the playout process is alive but the HLS stream has stopped
     * advancing (no fresh .m3u8 or segment for $maxStaleSeconds). A frozen
     * ffmpeg otherwise looks "healthy" and would stall forever.
     */
    public function isStalled(AdminChannel $channel, int $maxStaleSeconds = 45): bool
    {
        if (! $this->isRunning($channel)) {
            return false; // dead process — handled separately by the watchdog
        }

        $streamDir = $this->streamDir($channel);
        if (! is_dir($streamDir)) {
            return false;
        }

        $newest = 0;

        $playlist = "{$streamDir}/index.m3u8";
        if (is_file($playlist)) {
            $newest = max($newest, (int) @filemtime($playlist));
        }

        foreach (glob("{$streamDir}/segment_*.ts") ?: [] as $seg) {
            $newest = max($newest, (int) @filemtime($seg));
        }

        if ($newest === 0) {
            // No segments yet — a recently launched playout gets a grace
            // period to write its first segment before being called stalled.
            $dirAge = time() - (int) @filemtime($streamDir);
            return $dirAge > $maxStaleSeconds * 2;
        }

        return (time() - $newest) > $maxStaleSeconds;
    }

    /**
     * Apply overlay changes to a live channel.
     *
     * Ticker text and clock are purely dynamic — just overwrite ticker.txt and
     * FFmpeg picks it up on the next frame (reload=1). Zero restart.
     *
     * Logo/watermark require a restart only when the image file, position,
     * opacity, or enabled state actually changed — because those are baked into
     * the FFmpeg -i inputs and filtergraph at launch time.
     */
    public function applyOverlayUpdate(AdminChannel $channel, array $changed): void
    {
        $streamDir = $this->streamDir($channel);

        if (! File::isDirectory($streamDir)) {
            return;
        }

        // Always rewrite ticker.txt — FFmpeg re-reads it every frame
        $this->writeTickerFile($streamDir, $channel);

        // Image overlays need a restart only if something structural changed
        $imageFields = [
            'enable_overlay_logo', 'logo_url',
            'overlay_logo_position', 'overlay_logo_x', 'overlay_logo_y',
            'overlay_logo_size', 'overlay_logo_opacity',
            'enable_watermark', 'watermark_url',
            'watermark_position', 'watermark_opacity',
        ];

        $needsRestart = ! empty(array_intersect(array_keys($changed), $imageFields));

        if ($needsRestart) {
            $this->restartKeepingSegments($channel);
        }

        Log::info('Overlay update applied', [
            'channel_id'    => $channel->id,
            'restarted'     => $needsRestart,
            'changed_fields' => array_keys($changed),
        ]);
    }

    // ─── Prepare stage (normalize content) ──────────────────────────────────

    /**
     * Ensure every piece of playable content for a channel has a fresh
     * prepared intermediate. Returns prepared/skipped/failed counts. Safe to
     * call repeatedly — up-to-date files are skipped via a signature file.
     */
    public function prepareChannel(AdminChannel $channel, bool $force = false): array
    {
        $stats = ['prepared' => 0, 'skipped' => 0, 'failed' => []];
        $slug  = $channel->channel_slug;

        if (! $channel->is_my_channel) {
            return $stats;
        }

        $this->ensureDirectory("{$this->normalizedRoot}/{$slug}");

        $entries = MyChannelPlaylist::where('channel_id', $channel->id)
            ->orderBy('order_index')
            ->with('content')
            ->get();

        foreach ($entries as $entry) {
            if (! $entry->content || ! $entry->content->file_path) {
                continue;
            }
            $src = Storage::disk('public')->path($entry->content->file_path);
            if (! File::exists($src)) {
                continue;
            }
            try {
                $preExists = is_file($this->preparedPathFor($slug, $entry->content->id));
                $this->prepareFile($channel, $entry->content->id, $src, $entry, $force);
                if ($force || ! $preExists) {
                    $stats['prepared']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['failed'][] = $entry->content->title . ': ' . $e->getMessage();
                Log::error('Content prepare failed', [
                    'channel_id' => $channel->id,
                    'content_id' => $entry->content->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Playout is strictly limited to playlist-tab items. Orphaned files
        // in the upload directory are intentionally NOT part of the playout.

        return $stats;
    }

    /**
     * Transcode a single content record into the canonical playout format.
     * Returns the absolute path of the prepared intermediate (existing file is
     * reused when its signature matches the source + bake settings).
     *
     * @throws \RuntimeException when the transcode fails
     */
    public function prepareFile(
        AdminChannel $channel,
        int|string $contentId,
        string $srcPath,
        ?MyChannelPlaylist $entry = null,
        bool $force = false
    ): string {
        $slug = $channel->channel_slug;
        $this->ensureDirectory("{$this->normalizedRoot}/{$slug}");

        $dest = $this->preparedPathFor($slug, $contentId);
        $sig  = $dest . '.sig.json';

        $resolution = $channel->output_resolution ?: '1280x720';
        $bitrate    = (int) ($channel->output_bitrate ?: 2200);
        $fps        = (int) ($channel->output_frame_rate ?: 25);
        $height     = $this->resolutionHeight($resolution);
        $device     = strtolower($channel->transcoding_device ?? 'cpu');

        $start = 0;
        $duration = 0;
        if ($entry) {
            if ((int) $entry->custom_duration > 0) {
                $start    = (int) $entry->start_offset;
                $duration = (int) $entry->custom_duration;
            } elseif ((int) $entry->end_offset > 0) {
                $start    = (int) $entry->start_offset;
                $duration = (int) $entry->end_offset - (int) $entry->start_offset;
            } elseif ((int) $entry->start_offset > 0) {
                $start = (int) $entry->start_offset;
            }
        }

        $signature = [
            'src'  => realpath($srcPath),
            'mtime' => filemtime($srcPath),
            'size' => filesize($srcPath),
            'fps'  => $fps,
            'height' => $height,
            'bitrate' => $bitrate,
            'device'  => $device,
            'start'   => $start,
            'duration'=> $duration,
        ];

        if (! $force && is_file($dest) && is_file($sig)) {
            $stored = json_decode((string) @file_get_contents($sig), true);
            if (is_array($stored) && $stored === $signature) {
                return $dest;
            }
        }

        $tmp = $dest . '.tmp_' . getmypid() . '.mp4';

        $videoCodec = ($device === 'gpu' && $this->hasNvenc())
            ? "h264_nvenc -preset p4 -rc vbr -cq 26 -b:v 0 -maxrate {$bitrate}k -bufsize {$bitrate}k"
            : "libx264 -preset veryfast -crf 23 -maxrate {$bitrate}k -bufsize {$bitrate}k";

        $timeArgs = $duration > 0 ? ' -t ' . (int) $duration : '';

        $cmd = sprintf(
            '%s -y -hide_banner -loglevel warning -ss %d -i %s -map 0:v:0 -map 0:a:0? -vf %s -c:v %s -c:a aac -ac 2 -ar 48000 -b:a 128k%s -movflags +faststart %s',
            $this->ffmpeg,
            max(0, $start),
            escapeshellarg($srcPath),
            escapeshellarg("fps={$fps},scale=-2:{$height}:flags=lanczos,setsar=1,setpts=PTS-STARTPTS,format=yuv420p"),
            $videoCodec,
            $timeArgs,
            escapeshellarg($tmp)
        );

        @unlink($tmp);
        exec($cmd . ' 2>&1', $outLines, $rc);

        if ($rc !== 0 || ! is_file($tmp) || filesize($tmp) < 1024) {
            @unlink($tmp);
            if ($rc !== 0) {
                $err = implode(' | ', array_slice($outLines, -6));
                throw new \RuntimeException("ffmpeg prepare failed rc={$rc}: {$err}");
            }
            throw new \RuntimeException('prepared output missing');
        }

        // Guarantee an audio track: a clip with no audio must not produce an
        // intermediate that later breaks the playout's amix filter.
        $this->ensureAudioTrack($tmp);

        rename($tmp, $dest);
        File::put($sig, json_encode($signature));

        if (is_int($contentId)) {
            MyChannelContent::whereKey($contentId)->update(['prepared_at' => now()]);
        }

        Log::info('Content prepared for playout', [
            'channel_id'  => $channel->id,
            'content_id'  => $contentId,
            'dest'        => $dest,
            'size'        => filesize($dest),
            'est_duration_sec' => round(filesize($dest) / max(1, $bitrate * 1024 / 8), 1),
        ]);

        return $dest;
    }

    private function ensureAudioTrack(string $prepared): void
    {
        exec(
            sprintf('%s -v error -select_streams a -show_entries stream=codec_type -of csv=p=0 %s', $this->ffprobe, escapeshellarg($prepared)),
            $hasAudio, $rc
        );
        if ($rc === 0 && ! empty(array_filter($hasAudio))) {
            return;
        }

        $tmp = $prepared . '.noaud.tmp.mp4';
        exec(
            sprintf(
                '%s -y -v error -i %s -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=48000 -c:v copy -c:a aac -ac 2 -ar 48000 -b:a 128k -shortest -movflags +faststart %s',
                $this->ffmpeg,
                escapeshellarg($prepared),
                escapeshellarg($tmp)
            ),
            $out, $rc
        );
        if ($rc === 0 && is_file($tmp) && filesize($tmp) > 1024) {
            unlink($prepared);
            rename($tmp, $prepared);
        } elseif (is_file($tmp)) {
            @unlink($tmp);
        }
    }

    private function preparedPathFor(string $slug, int|string $id): string
    {
        return "{$this->normalizedRoot}/{$slug}/prepared_{$id}.mp4";
    }

    // ─── Overlay helpers ─────────────────────────────────────────────────────

    /**
     * Write ticker.txt and copy image assets to fixed filenames in the stream
     * directory. FFmpeg reads these paths at startup; ticker.txt is also
     * re-read every frame via reload=1.
     */
    private function writeOverlayAssets(string $streamDir, AdminChannel $channel): void
    {
        $this->writeTickerFile($streamDir, $channel);
        $this->copyImageAsset(
            $channel->enable_overlay_logo ? $channel->logo_url : null,
            "{$streamDir}/logo.png"
        );
        $this->copyImageAsset(
            $channel->enable_watermark ? $channel->watermark_url : null,
            "{$streamDir}/watermark.png"
        );
    }

    private function writeTickerFile(string $streamDir, AdminChannel $channel): void
    {
        $text = ($channel->enable_ticker && $channel->ticker_text)
            ? $channel->ticker_text
            : '';
        File::put("{$streamDir}/ticker.txt", $text);
    }

    /**
     * Copy an overlay image to a fixed destination path.
     * Uses atomic rename (copy→tmp→rename) so FFmpeg never reads a partial file.
     * If source is missing, writes a 1×1 transparent PNG placeholder so the
     * filtergraph stays valid.
     */
    private function copyImageAsset(?string $url, string $dest): void
    {
        $src = $url ? $this->urlToLocalPath($url) : null;

        if ($src && File::exists($src)) {
            $tmp = $dest . '.tmp';
            File::copy($src, $tmp);
            rename($tmp, $dest);
            return;
        }

        if (! File::exists($dest)) {
            // 1×1 transparent PNG
            file_put_contents($dest, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            ));
        }
    }

    /**
     * Restart FFmpeg in-place: kill the current process, keep the stream
     * directory and all existing segments, relaunch from the next segment
     * number so HLS clients buffering the last ~12 segments see no gap.
     */
    public function restartKeepingSegments(AdminChannel $channel): void
    {
        $streamDir = $this->streamDir($channel);

        // Find next segment number
        $segments    = glob("{$streamDir}/segment_*.ts") ?: [];
        $nextSegment = 0;
        if (! empty($segments)) {
            natsort($segments);
            $last        = basename((string) end($segments), '.ts');
            $nextSegment = (int) substr($last, 8) + 1;
        }

        // Kill current FFmpeg but leave stream dir intact
        $pid = (int) Cache::get($this->cacheKey($channel));
        $this->killPlayout($pid, $streamDir);
        Cache::forget($this->cacheKey($channel));

        // Refresh image assets with new files before rebuilding script
        $this->writeOverlayAssets($streamDir, $channel);

        $playlist = $this->resolvePlaylist($channel);
        $files    = $this->collectFiles($playlist, $channel);
        if (empty($files)) {
            return;
        }

        $loopScript = $this->writeLoopScript($streamDir, $files, $channel, $nextSegment);
        $pid        = $this->launchScript($loopScript, $streamDir);

        if ($pid) {
            Cache::put($this->cacheKey($channel), $pid, 86400);
        }
    }

    private function resolvePlaylist(AdminChannel $channel): Collection
    {
        $items = MyChannelPlaylist::where('channel_id', $channel->id)
            ->orderBy('order_index')
            ->with('content')
            ->get()
            ->map(fn ($item) => $item->content)
            ->filter();

        if ($channel->shuffle_mode) {
            $items = $items->shuffle();
        }

        return $items->values();
    }

    /**
     * Resolve the files ffmpeg should play. Prefers the prepared intermediate
     * for each content record (uniform codec/CFR/audio) and falls back to the
     * raw source when a prepared file is not (yet) available.
     */
    private function collectFiles(Collection $playlist, AdminChannel $channel): array
    {
        $files        = [];
        $slug         = $channel->channel_slug;

        foreach ($playlist as $content) {
            if (! $content || ! $content->file_path) {
                continue;
            }

            $absolute = Storage::disk('public')->path($content->file_path);

            if (! File::exists($absolute)) {
                Log::warning('My channel content file missing, skipping', [
                    'content_id' => $content->id ?? null,
                    'path'       => $absolute,
                ]);
                continue;
            }

            if ($content->id > 0) {
                $prepared = $this->preparedPathFor($slug, (int) $content->id);
                if (is_file($prepared)) {
                    $files[] = $prepared;
                    continue;
                }
            }

            $files[] = $absolute;
        }

        $files = array_values(array_unique($files));

        // Trim the concat list to files that actually exist right now; a stale
        // path in the middle would make the whole ffmpeg run abort.
        return array_filter($files, fn ($f) => File::exists($f));
    }

    /**
     * Build the playout shell script.
     *
     * Timestamp design: input PTS resets at every concat boundary, which made
     * the upstream ffmpeg (with -r CFR) duplicate thousands of frames and
     * segfault NVENC after the first item boundary — the "video cuts off at
     * the end" bug. The fps + setpts filters below rebuild the output timeline
     * as a pure frame counter, so timestamps are strictly monotonic forever.
     *
     * Overlay design:
     *   - Logo / watermark: -i inputs pointing to fixed filenames in stream dir.
     *     Updating the image = copy new file to that path + restart.
     *   - Ticker text: drawtext reads ticker.txt with reload=1 every frame.
     *     Updating text = overwrite ticker.txt, zero restart.
     *   - Clock: drawtext strftime, always live.
     */
    private function writeLoopScript(string $streamDir, array $files, AdminChannel $channel, int $startSegment = 0): string
    {
        $scriptPath = "{$streamDir}/playout.sh";
        $ffmpeg     = $this->ffmpeg;

        $resolution = $channel->output_resolution ?: '1280x720';
        $bitrate    = $channel->output_bitrate    ?: 2200;
        $fps        = (int) ($channel->output_frame_rate ?: 25);
        $height     = $this->resolutionHeight($resolution);
        $width      = $this->resolutionWidth($resolution);
        $gop        = $fps * 2;

        $concatPath  = "{$streamDir}/concat.txt";
        // Repeat the file list 500 times so one ffmpeg run loops for days
        // without needing a restart. The while true wrapper handles the rare
        // case where ffmpeg exits after exhausting all repetitions or crashes.
        // NOTE: -stream_loop -1 is intentionally NOT used — it is silently
        // ignored by the concat demuxer and causes a segfault at EOF.
        $concatLines = array_map(fn ($f) => 'file ' . escapeshellarg($f), $files);
        $singlePass  = implode("\n", $concatLines) . "\n";
        File::put($concatPath, str_repeat($singlePass, 500));

        [$extraInputs, $filterComplex, $hasImages] = $this->buildFiltergraph(
            $streamDir, $channel, $width, $height, $fps
        );

        $inputLines  = $hasImages ? $extraInputs . "\\\n    " : '';
        $startNum    = $startSegment > 0 ? "-start_number {$startSegment} " : '';

        $videoCodec = (strtolower($channel->transcoding_device ?? 'cpu') === 'gpu' && $this->hasNvenc())
            ? 'h264_nvenc -preset p4 -tune ll -rc vbr -cq 28 -b:v 0'
            : 'libx264 -preset ultrafast -tune zerolatency -crf 28 -threads 2';

        $script = <<<BASH
#!/bin/sh
STREAM_DIR="{$streamDir}"
FFMPEG="{$ffmpeg}"
CONCAT="{$concatPath}"

# Next segment number = highest segment on disk + 1. Keeps the HLS playlist
# continuous across any crash/restart instead of resetting to 0.
next_segment() {
    last=\$(ls "\$STREAM_DIR"/segment_*.ts 2>/dev/null | sed 's/.*segment_0*//;s/\.ts\$//' | sort -n 2>/dev/null | tail -1)
    if [ -n "\$last" ]; then
        echo \$((last + 1))
    else
        echo 0
    fi
}

while true; do
    N=\$(next_segment)
    if [ "\$N" -gt 0 ]; then
        START_N="-start_number \$N"
    else
        START_N=""
    fi
    echo "PLAYOUT START seg=\$N \$(date +%s)" >> "\$STREAM_DIR/ffmpeg.log"
    "\$FFMPEG" -y -hide_banner -loglevel warning \\
        -fflags +genpts+igndts -f concat -safe 0 -re -i "\$CONCAT" \\
        -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=48000 \\
        {$inputLines}-c:v {$videoCodec} \\
        -maxrate {$bitrate}k -bufsize {$bitrate}k -g {$gop} -pix_fmt yuv420p \\
        -filter_complex "{$filterComplex}" \\
        -map '[vout]' -map '[aout]' \\
        -c:a aac -b:a 128k -ac 2 -ar 48000 \\
        -f hls -hls_time {$this->segmentDuration} -hls_list_size {$this->playlistSize} \\
        -hls_flags independent_segments+delete_segments+append_list \\
        -hls_allow_cache 0 \\
        -hls_segment_type mpegts \\
        -max_muxing_queue_size 4096 \\
        {$startNum}\$START_N -hls_segment_filename "\$STREAM_DIR/segment_%05d.ts" \\
        "\$STREAM_DIR/index.m3u8" >> "\$STREAM_DIR/ffmpeg.log" 2>&1
    echo "PLAYOUT EXIT rc=\$? \$(date +%s)" >> "\$STREAM_DIR/ffmpeg.log"
    sleep 3
done
BASH;

        File::put($scriptPath, $script);
        chmod($scriptPath, 0755);

        return $scriptPath;
    }

    /**
     * Build the -filter_complex graph and any extra -i input lines.
     *
     * Images (logo, watermark) are passed as numbered -i inputs so FFmpeg
     * reads them once at startup from the fixed filenames in the stream dir.
     * Ticker uses drawtext textfile+reload=1 so it re-reads ticker.txt every
     * frame — text changes take effect immediately with no restart.
     * Audio is always mixed with anullsrc silence (clips without audio would
     * otherwise abort amix) and normalized to a 48k sample counter.
     *
     * Returns [string $extraInputLines, string $filterComplex, bool $hasImages]
     */
    private function buildFiltergraph(string $streamDir, AdminChannel $channel, int $width, int $height, int $fps): array
    {
        $extraInputs = [];
        $filters     = [];
        $lastVideo   = '[vscaled]';

        // anullsrc is always input index 1 (after concat); image overlays start at 2
        $inputIndex  = 2;
        $audioInputIndex = 1;

        // Mix real audio (if present) with silent fallback so files without audio don't stall
        $audioFilter = "[0:a][{$audioInputIndex}:a]amix=inputs=2:duration=first:dropout_transition=0,aresample=48000:async=1,asetpts=N/SR/TB[aout]";
        $filters[] = "[0:v]scale=-2:{$height}:flags=lanczos,setsar=1[vscaled]";

        // ── Logo ─────────────────────────────────────────────────────────────
        if ($channel->enable_overlay_logo) {
            $logoFile = "{$streamDir}/logo.png";
            $extraInputs[] = "-i " . escapeshellarg($logoFile);
            $in = "[{$inputIndex}:v]";
            $inputIndex++;

            $logoW = max(20, (int) round($width * ($channel->overlay_logo_size / 100.0) * 0.15));
            [$xPx, $yPx] = $this->resolveOverlayXY(
                $channel->overlay_logo_position,
                $channel->overlay_logo_x,
                $channel->overlay_logo_y,
                $width, $height, $logoW, (int) round($logoW * 0.5)
            );
            $opacity = number_format((float) $channel->overlay_logo_opacity, 2, '.', '');

            $filters[] = "{$in}scale={$logoW}:-1:flags=lanczos,format=rgba,colorchannelmixer=aa={$opacity}[logo]";
            $filters[] = "{$lastVideo}[logo]overlay={$xPx}:{$yPx}[vlogo]";
            $lastVideo  = '[vlogo]';
        }

        // ── Watermark ────────────────────────────────────────────────────────
        if ($channel->enable_watermark) {
            $wmFile = "{$streamDir}/watermark.png";
            $extraInputs[] = "-i " . escapeshellarg($wmFile);
            $in = "[{$inputIndex}:v]";
            $inputIndex++;

            $wmW = (int) round($width * 0.12);
            [$wmX, $wmY] = $this->positionToXY(
                $channel->watermark_position ?: 'bottom-right',
                $width, $height, $wmW, (int) round($wmW * 0.5)
            );
            $wmOpacity = number_format((float) $channel->watermark_opacity, 2, '.', '');

            $filters[] = "{$in}scale={$wmW}:-1:flags=lanczos,format=rgba,colorchannelmixer=aa={$wmOpacity}[wm]";
            $filters[] = "{$lastVideo}[wm]overlay={$wmX}:{$wmY}[vwm]";
            $lastVideo  = '[vwm]';
        }

        // ── Ticker — textfile+reload=1, zero restart on text changes ─────────
        if ($channel->enable_ticker) {
            $tickerFile = "{$streamDir}/ticker.txt";
            $color      = ltrim($channel->ticker_color ?: '#ffffff', '#');
            $bgColor    = $this->hexToFfmpegColor($channel->ticker_background ?: '#000000cc');
            $fontsize   = max(16, (int) round($height * 0.035));
            $barH       = $fontsize + 12;
            $yPos       = $height - $barH;
            $speed      = (int) round(80 + (($channel->ticker_speed - 10) / 90) * 320);
            $xExpr      = $channel->ticker_direction === 'right'
                ? "mod(t*{$speed}\\,w+tw)"
                : "w-mod(t*{$speed}\\,w+tw)";
            $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $tickerFile);

            $filters[] = "{$lastVideo}drawbox=x=0:y={$yPos}:w=iw:h={$barH}:color={$bgColor}:t=fill," .
                         "drawtext=textfile='{$escaped}':reload=1:fontcolor=0x{$color}:fontsize={$fontsize}" .
                         ":x='{$xExpr}':y={$yPos}+6[vticker]";
            $lastVideo  = '[vticker]';
        }

        // ── Clock — strftime, always live ────────────────────────────────────
        if ($channel->enable_overlay_clock) {
            $timeExpr = $this->clockFormatToFfmpeg($channel->overlay_clock_format ?: 'HH:MM:SS');
            $fontsize  = max(14, (int) round($height * 0.03));
            $pad       = 8;
            [$cX, $cY] = $this->resolveOverlayXY(
                $channel->overlay_clock_position,
                $channel->overlay_clock_x,
                $channel->overlay_clock_y,
                $width, $height, $fontsize * 9, $fontsize + ($pad * 2)
            );

            $filters[] = "{$lastVideo}drawtext=expansion=strftime:text='{$timeExpr}':fontcolor=white" .
                         ":fontsize={$fontsize}:box=1:boxcolor=black@0.5:boxborderw={$pad}:x={$cX}:y={$cY}[vclock]";
            $lastVideo  = '[vclock]';
        }

        // Rebuild video PTS as a strict frame counter so the concat boundaries
        // stay timestamp-continuous (fixes the old NVENC dup/segfault/cut).
        $filters[] = "{$lastVideo}fps={$fps},setpts=N/({$fps}*TB)[vout]";
        $filters[] = $audioFilter;

        $hasImages       = ! empty($extraInputs);
        $extraInputLines = $hasImages ? implode(" \\\n    ", $extraInputs) : '';
        $filterComplex   = implode(';', $filters);

        return [$extraInputLines, $filterComplex, $hasImages];
    }

    private function urlToLocalPath(string $url): ?string
    {
        $url = strtok($url, '?');

        if (preg_match('#/storage/(.+)$#', $url, $m)) {
            return Storage::disk('public')->path($m[1]);
        }

        if (str_starts_with($url, '/') && File::exists($url)) {
            return $url;
        }

        return null;
    }

    private function resolveOverlayXY(?string $preset, mixed $x, mixed $y, int $w, int $h, int $elemW, int $elemH): array
    {
        if ($x !== null && $y !== null) {
            return [
                (int) round($w * ((float) $x / 100.0)),
                (int) round($h * ((float) $y / 100.0)),
            ];
        }

        return $this->positionToXY($preset ?: 'top-left', $w, $h, $elemW, $elemH);
    }

    private function positionToXY(string $position, int $w, int $h, int $elemW, int $elemH, int $pad = 10): array
    {
        $x = match (true) {
            str_contains($position, 'right')  => $w - $elemW - $pad,
            str_contains($position, 'center') => (int) round(($w - $elemW) / 2),
            default                            => $pad,
        };
        $y = match (true) {
            str_contains($position, 'bottom') => $h - $elemH - $pad,
            str_contains($position, 'center') => (int) round(($h - $elemH) / 2),
            default                            => $pad,
        };
        return [$x, $y];
    }

    private function hexToFfmpegColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 8) {
            $alpha = round(hexdec(substr($hex, 6, 2)) / 255, 2);
            return '0x' . strtoupper(substr($hex, 0, 6)) . '@' . $alpha;
        }
        return '0x' . strtoupper(substr($hex, 0, 6));
    }

    private function clockFormatToFfmpeg(string $fmt): string
    {
        return match ($fmt) {
            'HH:MM'      => '%H\\:%M',
            'MM/DD/YYYY' => '%m/%d/%Y',
            'YYYY-MM-DD' => '%Y-%m-%d',
            default      => '%H\\:%M\\:%S',
        };
    }

    private function launchScript(string $scriptPath, string $streamDir): ?int
    {
        $cmd    = "setsid nohup {$scriptPath} > {$streamDir}/playout.log 2>&1 & echo \$!";
        $output = [];

        @exec($cmd, $output);

        $pid = (int) (end($output) ?: 0);

        if ($pid > 0) {
            // Persist the PID next to the stream so liveness checks survive
            // cache flushes and cross-process cache drivers.
            @file_put_contents("{$streamDir}/playout.pid", (string) $pid);
        }

        return $pid > 0 ? $pid : null;
    }

    private function streamDir(AdminChannel $channel): string
    {
        return "{$this->segmentRoot}/admin-channel-{$channel->channel_slug}";
    }

    private function resolutionHeight(string $resolution): int
    {
        $parts = explode('x', strtolower($resolution));
        return (isset($parts[1]) && is_numeric($parts[1])) ? (int) $parts[1] : 720;
    }

    private function resolutionWidth(string $resolution): int
    {
        $parts = explode('x', strtolower($resolution));
        return (isset($parts[0]) && is_numeric($parts[0])) ? (int) $parts[0] : 1280;
    }

    private function cacheKey(AdminChannel $channel): string
    {
        return "mychannel_hls:{$channel->id}";
    }

    private function hasNvenc(): bool
    {
        static $cached;
        if ($cached === null) {
            exec('ffmpeg -hide_banner -encoders 2>/dev/null', $output, $exit);
            $cached = $exit === 0 && str_contains(implode("\n", $output), 'h264_nvenc');
        }
        return $cached;
    }

    private function ensureDirectory(string $path): void
    {
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }
}