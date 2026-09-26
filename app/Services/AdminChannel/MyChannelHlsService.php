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
 * 1. PREPARE  — every playlist item is baked once into a canonical
 *    intermediate with *rigid* normalisation: exact WxH (letterboxed, never
 *    distorted), constant frame rate, fixed H.264 profile + GOP, PTS starting
 *    at zero, 48 kHz stereo AAC, and an ffprobe verification pass. Mixed
 *    codecs / frame rates / aspect ratios can no longer reach playout.
 * 2. STAGE 1 (PLAYOUT) — a long-lived ffmpeg reads those prepared files
 *    through the concat demuxer and `-c copy`s them into ONE continuous
 *    mpegts stream written to a FIFO. Because nothing is re-encoded this stage
 *    costs ~0% CPU, and because it emits a single stream it is the only place
 *    concat boundary timestamps are ever reconciled.
 * 3. STAGE 2 (ENCODE) — a second ffmpeg reads the FIFO, applies the overlay
 *    filtergraph and writes HLS. It never sees a file boundary, so its
 *    filtergraph and encoder never re-negotiate mid-stream.
 * 4. WRAPPER  — one supervisor holds the FIFO open RDWR (so neither stage can
 *    send the other EOF) and runs both stages in supervised loops. Stage 1 is
 *    restarted for playlist edits while Stage 2 keeps encoding; Stage 2 is
 *    restarted for encoder trouble while Stage 1 keeps playing. The HLS
 *    segment number always continues from what is on disk.
 * 5. WATCHDOG — process death *and* freeze detection (stream stopped advancing)
 *    both trigger a restart; a broadcast only ends when the admin stops it.
 */
class MyChannelHlsService
{
    /**
     * Bump when the normalisation recipe changes; invalidates every baked
     * intermediate in the field so it is re-prepared on the next pass.
     */
    private const NORMALIZE_VERSION = 2;

    /**
     * Overlay fields that are compiled into the Stage 2 filtergraph string
     * itself, so changing one requires an encoder restart.
     */
    private const GRAPH_FIXED_OVERLAY_FIELDS = [
        'enable_ticker', 'ticker_color', 'ticker_background',
        'ticker_speed', 'ticker_direction',
        'enable_overlay_clock', 'overlay_clock_position',
        'overlay_clock_x', 'overlay_clock_y', 'overlay_clock_format',
    ];

    /**
     * Overlay fields baked into the canvas image rather than the graph. In the
     * default png canvas mode these are picked up live, with no restart.
     */
    private const IMAGE_OVERLAY_FIELDS = [
        'enable_overlay_logo', 'logo_url',
        'overlay_logo_position', 'overlay_logo_x', 'overlay_logo_y',
        'overlay_logo_size', 'overlay_logo_opacity',
        'enable_watermark', 'watermark_url',
        'watermark_position', 'watermark_opacity',
    ];

    private string $segmentRoot;
    private string $normalizedRoot;
    private string $ramRoot;
    private string $ffmpeg;
    private string $ffprobe;
    private int $segmentDuration;
    // Larger live window so a 1–2s wrapper restart does not starve players
    // (18 × 2s ≈ 36s buffer). Critical for 24/7 unattended playout.
    private int $playlistSize;

    public function __construct()
    {
        $this->segmentRoot    = storage_path('app/streams/hls');
        $this->normalizedRoot = storage_path('app/streams/normalized');
        // /dev/shm is a native Linux tmpfs RAM disk. High-frequency overlay
        // files (ticker.txt, the overlay canvas) are written here so FFmpeg's
        // per-frame reads come from RAM instead of disk.
        $this->ramRoot        = '/dev/shm/studio';
        $this->ffmpeg         = config('streaming.transcoding.ffmpeg_path', '/usr/bin/ffmpeg');
        $this->ffprobe        = config('streaming.transcoding.ffprobe_path', '/usr/bin/ffprobe');
        $this->segmentDuration = max(1, (int) config('playout.segment_duration', 2));
        $this->playlistSize    = max(1, (int) config('playout.playlist_size', 18));
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
                throw new \RuntimeException('No prepared media files available for playout');
            }

            // Overlay canvas + ticker live on the RAM disk, ready before Stage 2
            // opens them, so the very first frame already carries the overlays.
            $this->writeOverlayAssets($streamDir, $channel);
            $canvasMode = $this->resolveCanvasMode($channel);

            // Segment numbering is discovered by Stage 2 at launch time, so
            // any restart appends to the playlist already on disk.
            $loopScript = $this->writePlayoutScript(
                $streamDir, $files, $channel, $canvasMode
            );
        } catch (\Throwable $e) {
            $broadcast->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            Log::error('Failed to build playout script', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            return false;
        }

        // Admission gate: encoding on a box that is already saturated would
        // starve Flussonic and XC-VM. Refuse rather than freeze everything.
        if (! $this->loadGateOpen()) {
            $msg = 'Server load too high to start playout (load gate)';
            $broadcast->update(['status' => 'error', 'error_message' => $msg]);
            Log::warning('My channel playout refused by load gate', ['channel_id' => $channel->id]);
            return false;
        }

        $pid = $this->launchScript($loopScript, $streamDir, $channel);

        if (! $pid) {
            $broadcast->update(['status' => 'error', 'error_message' => 'Failed to launch playout script']);
            return false;
        }

        Cache::put($this->cacheKey($channel), $pid, 86400);

        $broadcast->update(['status' => 'running', 'start_time' => now(), 'error_message' => null]);
        $channel->update(['broadcast_status' => 'live']);

        Log::info('My channel two-stage playout started', [
            'channel_id'  => $channel->id,
            'pid'         => $pid,
            'output'      => $streamDir,
            'sources'     => count($files),
            'canvas_mode' => $canvasMode,
        ]);

        return true;
    }

    public function stop(AdminChannel $channel): void
    {
        $pid = (int) Cache::get($this->cacheKey($channel));
        $streamDir = $this->streamDir($channel);

        $this->stopUnit($channel);
        $this->killPlayout($pid, $streamDir);

        Cache::forget($this->cacheKey($channel));
        Log::info('Playout terminated', ['channel_id' => $channel->id, 'pid' => $pid]);

        if (File::isDirectory($streamDir)) {
            File::deleteDirectory($streamDir);
        }
        @unlink($this->ramDir($channel) . '/overlay.png');
        @unlink($this->ramDir($channel) . '/ticker.txt');
    }

    public function softStop(AdminChannel $channel): void
    {
        $pid = (int) Cache::get($this->cacheKey($channel));
        $streamDir = $this->streamDir($channel);

        $this->stopUnit($channel);
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

        foreach (glob("{$streamDir}/seg_*.ts") ?: [] as $seg) {
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
     * Never restarts Stage 1 (the playout / concat side), so the playlist
     * timeline is untouched:
     *
     *   • ticker text      — ticker.txt, re-read every frame (reload=1)
     *   • logo & watermark — image, position, size, opacity and on/off are all
     *                        baked into the full-frame RGBA canvas that Stage 2
     *                        re-reads, so a rewrite is picked up with no
     *                        restart at all
     *   • ticker styling / clock config — these are literals inside the
     *                        filtergraph, so they need a Stage-2-only restart.
     *                        Stage 1 keeps playing and the FIFO keeps the
     *                        encoder fed, so the HLS playlist is never rebuilt.
     */
    public function applyOverlayUpdate(AdminChannel $channel, array $changed): void
    {
        $streamDir = $this->streamDir($channel);

        if (! File::isDirectory($streamDir)) {
            return;
        }

        // Ticker text is re-read by drawtext every frame, so a rewrite is all
        // it takes. The canvas is re-read by Stage 2 on its loop, but only
        // re-render it when something that lives on the canvas actually moved —
        // a colour or clock change should not pay for an ffmpeg exec.
        $imagesChanged = ! empty(array_intersect(array_keys($changed), self::IMAGE_OVERLAY_FIELDS));
        $this->writeOverlayAssets($streamDir, $channel, $imagesChanged);

        $needsEncoderRestart = $this->encoderRestartRequired($channel, $changed);
        $restarted = $needsEncoderRestart ? $this->restartEncoder($channel) : false;

        Log::info('Overlay update applied', [
            'channel_id'      => $channel->id,
            'encoder_restart' => $restarted,
            'restart_reason'  => $this->restartReason($changed, $needsEncoderRestart),
            'canvas_rerender' => $imagesChanged,
            'changed_fields'  => array_keys($changed),
        ]);
    }

    private function restartReason(array $changed, bool $restart): ?string
    {
        if (! $restart) {
            return null;
        }

        return array_intersect(array_keys($changed), self::GRAPH_FIXED_OVERLAY_FIELDS) !== []
            ? 'filtergraph field changed'
            : 'canvas is static, so an image field changed';
    }

    /**
     * Does an overlay change require restarting Stage 2?
     *
     * Public and side-effect free so the contract can be asserted directly:
     * ticker text and (in live canvas mode) logo/watermark edits are free,
     * while anything compiled into the filtergraph string restarts the
     * encoder only — Stage 1 and the concat list are never touched.
     */
    public function encoderRestartRequired(AdminChannel $channel, array $changed): bool
    {
        $changedKeys = array_keys($changed);

        if (! empty(array_intersect($changedKeys, self::GRAPH_FIXED_OVERLAY_FIELDS))) {
            return true;
        }

        // Image fields are live on the canvas input, except in static mode
        // where the canvas is decoded once at launch.
        return ! $this->canvasIsLive($channel)
            && ! empty(array_intersect($changedKeys, self::IMAGE_OVERLAY_FIELDS));
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
        $fps        = max(1, (int) ($channel->output_frame_rate ?: 25));
        // Both dimensions are pinned (even for yuv420p) so every prepared
        // intermediate has byte-identical geometry. Without a pinned width the
        // old `scale=-2:{h}` produced a different width per aspect ratio, so
        // neither Stage 1 `-c copy` nor a stable output resolution was ever
        // possible.
        $width      = $this->evenDimension($this->resolutionWidth($resolution));
        $height     = $this->evenDimension($this->resolutionHeight($resolution));
        $device     = strtolower($channel->transcoding_device ?? 'cpu');
        $profile    = $this->h264ProfileFor($width, $height);
        $gop        = $fps * 2;

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
            'width' => $width,
            'height' => $height,
            'bitrate' => $bitrate,
            'device'  => $device,
            'profile' => $profile,
            'gop'     => $gop,
            'start'   => $start,
            'duration'=> $duration,
            // Bump whenever the normalisation recipe changes so every
            // intermediate in the field is re-baked on the next prepare pass.
            'norm'    => self::NORMALIZE_VERSION,
        ];

        if (! $force && is_file($dest) && is_file($sig)) {
            $stored = json_decode((string) @file_get_contents($sig), true);
            if (is_array($stored) && $stored === $signature) {
                if ($this->verifyPrepared($dest, $width, $height, $fps)) {
                    return $dest;
                }
                // Probe failed (truncated/corrupt file) — fall through and rebake.
                Log::warning('Prepared intermediate failed verification, re-baking', ['dest' => $dest]);
            }
        }

        $tmp = $dest . '.tmp_' . getmypid() . '.mp4';

        $videoCodec = ($device === 'gpu' && $this->hasNvenc())
            ? "h264_nvenc -preset p4 -rc vbr -cq 26 -b:v 0 -maxrate {$bitrate}k -bufsize {$bitrate}k"
            : "libx264 -preset veryfast -crf 23 -maxrate {$bitrate}k -bufsize {$bitrate}k";

        $timeArgs = $duration > 0 ? ' -t ' . (int) $duration : '';

        // Rigid normalisation: exact WxH (letterboxed, never distorted), CFR,
        // uniform SAR/colour, PTS starting at zero, one fixed H.264 profile and
        // GOP, identical 48 kHz stereo AAC. Stage 1 can then `-c copy` every
        // file into a single monotonic mpegts without re-timing anything.
        $filter = sprintf(
            'fps=%d,scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,setpts=PTS-STARTPTS,format=yuv420p',
            $fps,
            $width, $height,
            $width, $height
        );

        $cmd = sprintf(
            '%s -y -hide_banner -loglevel warning -ss %d -i %s -map 0:v:0 -map 0:a:0? -vf %s -c:v %s -profile:v %s -g %d -r %d -vsync cfr -c:a aac -profile:a aac_low -ac 2 -ar 48000 -b:a 128k -video_track_timescale 90000 -sn -dn%s -movflags +faststart %s',
            $this->ffmpeg,
            max(0, $start),
            escapeshellarg($srcPath),
            escapeshellarg($filter),
            $videoCodec,
            $profile,
            $gop,
            $fps,
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

        if (! $this->verifyPrepared($tmp, $width, $height, $fps)) {
            @unlink($tmp);
            throw new \RuntimeException('prepared output failed rigid normalisation checks');
        }

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

    /**
     * yuv420p needs both dimensions even, and both must be pinned so that
     * Stage 1 can `-c copy` every file into one stream with identical geometry.
     */
    private function evenDimension(int $value): int
    {
        $value = max(2, $value);

        return $value - ($value & 1);
    }

    /**
     * One fixed H.264 profile for every prepared intermediate, recorded in the
     * signature so changing it re-bakes the file rather than silently mixing
     * profiles inside a single `-c copy` concat.
     */
    private function h264ProfileFor(int $width, int $height): string
    {
        return ($width * $height) > (1280 * 720) ? 'high' : 'main';
    }

    /**
     * Rigid normalisation assertions for a prepared intermediate. Anything
     * that fails is re-baked, and — because playout only consumes verified
     * files — an un-normalised source can never reach Stage 1.
     *
     * Checks: H.264, exact geometry, constant frame rate, yuv420p, and an
     * audio track at 48 kHz (Stage 1 maps 0:a:0 unconditionally).
     */
    private function verifyPrepared(string $path, int $width, int $height, int $fps): bool
    {
        if (! is_file($path) || filesize($path) < 1024) {
            return false;
        }

        $cmd = sprintf(
            '%s -v error -show_entries stream=codec_type,codec_name,width,height,pix_fmt,r_frame_rate,avg_frame_rate,sample_rate,channels -of json %s',
            $this->ffprobe,
            escapeshellarg($path)
        );

        $out = [];
        exec($cmd, $out, $rc);

        if ($rc !== 0) {
            return false;
        }

        $json = json_decode(implode("\n", $out), true);

        if (! is_array($json) || empty($json['streams']) || ! is_array($json['streams'])) {
            return false;
        }

        $video = $audio = null;

        foreach ($json['streams'] as $stream) {
            $type = $stream['codec_type'] ?? '';
            if ($type === 'video' && $video === null) {
                $video = $stream;
            }
            if ($type === 'audio' && $audio === null) {
                $audio = $stream;
            }
        }

        if ($video === null) {
            return false;
        }

        if (($video['codec_name'] ?? '') !== 'h264'
            || (int) ($video['width'] ?? 0) !== $width
            || (int) ($video['height'] ?? 0) !== $height
            || ($video['pix_fmt'] ?? '') !== 'yuv420p'
        ) {
            return false;
        }

        $rateOk = $this->frameRateOf($video['r_frame_rate'] ?? '') === $fps
            || $this->frameRateOf($video['avg_frame_rate'] ?? '') === $fps;

        if (! $rateOk) {
            return false;
        }

        if ($audio === null || (int) ($audio['sample_rate'] ?? 0) !== 48000) {
            return false;
        }

        return true;
    }

    private function frameRateOf(string $rate): int
    {
        if (str_contains($rate, '/')) {
            [$num, $den] = array_pad(explode('/', $rate, 2), 2, '1');
            if (! is_numeric($num) || ! is_numeric($den) || (float) $den == 0.0) {
                return 0;
            }

            return (int) round(((float) $num) / (float) $den);
        }

        return is_numeric($rate) ? (int) round((float) $rate) : 0;
    }

    // ─── Overlay helpers ─────────────────────────────────────────────────────

    /**
     * Write every overlay artefact the encoder reads:
     *   • ticker.txt  → /dev/shm, re-read every frame by drawtext reload=1
     *   • overlay.raw → /dev/shm, a full-frame RGBA canvas with the logo and
     *                   watermark already composited onto it. Stage 2 overlays
     *                   this one input, so image / position / size / opacity /
     *                   enable changes are a file rewrite instead of a restart.
     *   • logo.png / watermark.png → kept beside the stream for debugging and
     *                   as the source the canvas is composed from.
     */
    private function writeOverlayAssets(string $streamDir, AdminChannel $channel, bool $renderCanvas = true): void
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

        if ($renderCanvas) {
            $this->writeOverlayCanvas($channel);
        }
    }

    /**
     * Render the overlay canvas: a transparent full-frame RGBA PNG with the
     * logo and watermark composited onto it, sitting on the RAM disk.
     *
     * Stage 2 opens it with the image2 demuxer and `-loop 1`, which re-opens
     * the file BY PATH for every packet it produces — so rewriting this file
     * is all it takes to change the logo, its position, size, opacity or its
     * enabled state on a live encoder. The rewrite is an atomic rename, so the
     * reader either sees the old complete PNG or the new one, never a
     * half-written image (a decode failure there would kill Stage 2).
     */
    private function writeOverlayCanvas(AdminChannel $channel): void
    {
        $dir = $this->ramDir($channel);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $resolution = $channel->output_resolution ?: '1280x720';
        $width  = $this->evenDimension($this->resolutionWidth($resolution));
        $height = $this->evenDimension($this->resolutionHeight($resolution));

        [$inputs, $graph] = $this->buildCanvasGraph($channel, $width, $height);

        // Both the encoder and the extension matter: the image2 muxer guesses
        // its codec from the filename, so a `.tmp` target would silently emit
        // MJPEG under a `.png` name and Stage 2 would then fail to decode it.
        $tmp  = "{$dir}/overlay.tmp.png";
        $dest = "{$dir}/overlay.png";

        $cmd = sprintf(
            '%s -y -hide_banner -loglevel error -f lavfi -i %s%s -filter_complex %s -map [cout] -frames:v 1 -c:v png -pix_fmt rgba -f image2 %s 2>&1',
            $this->ffmpeg,
            escapeshellarg(sprintf('color=c=black@0.0:s=%dx%d:r=1:d=1', $width, $height)),
            $inputs,
            escapeshellarg($graph),
            escapeshellarg($tmp)
        );

        @unlink($tmp);
        exec($cmd, $out, $rc);

        if ($rc !== 0 || ! is_file($tmp) || filesize($tmp) < 64) {
            @unlink($tmp);
            Log::warning('Overlay canvas render failed', [
                'channel_id' => $channel->id,
                'rc'         => $rc,
                'error'      => implode(' | ', array_slice($out, -4)),
            ]);
            $this->ensureCanvasPlaceholder($dest);
            return;
        }

        if (! @rename($tmp, $dest)) {
            @unlink($tmp);
            Log::warning('Overlay canvas rename failed', [
                'channel_id' => $channel->id,
                'path'       => $dest,
            ]);
        }
    }

    /**
     * Stage 2 refuses to start without a canvas, so a failed render must never
     * leave the path empty — drop a 1×1 transparent PNG instead. The overlay
     * filter accepts a smaller second input, so the stream stays up with no
     * visible change rather than crash-looping the encoder.
     */
    private function ensureCanvasPlaceholder(string $dest): void
    {
        if (is_file($dest)) {
            return;
        }

        @file_put_contents($dest, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }

    /**
     * Build the one-shot composition graph for the canvas.
     * Input 0 is the transparent colour base; inputs 1..n are the sprites.
     *
     * @return array{0: string, 1: string} [extra -i arguments, filter_complex]
     */
    private function buildCanvasGraph(AdminChannel $channel, int $width, int $height): array
    {
        $inputs  = '';
        $filters = [];
        $next    = '[0:v]';
        $idx     = 1;

        if ($channel->enable_overlay_logo) {
            $src = $this->urlToLocalPath((string) $channel->logo_url);
            if ($src && is_file($src)) {
                $logoW = max(20, (int) round($width * ((float) $channel->overlay_logo_size / 100.0) * 0.15));
                [$x, $y] = $this->resolveOverlayXY(
                    $channel->overlay_logo_position,
                    $channel->overlay_logo_x,
                    $channel->overlay_logo_y,
                    $width, $height, $logoW, (int) round($logoW * 0.5)
                );
                $opacity = number_format((float) $channel->overlay_logo_opacity, 2, '.', '');

                $inputs .= ' -i ' . escapeshellarg($src);
                $filters[] = "[{$idx}:v]scale={$logoW}:-1:flags=lanczos,format=rgba,colorchannelmixer=aa={$opacity}[lg]";
                $filters[] = "{$next}[lg]overlay={$x}:{$y}[vc1]";
                $next  = '[vc1]';
                $idx++;
            }
        }

        if ($channel->enable_watermark) {
            $src = $this->urlToLocalPath((string) $channel->watermark_url);
            if ($src && is_file($src)) {
                $wmW = (int) round($width * 0.12);
                [$x, $y] = $this->positionToXY(
                    $channel->watermark_position ?: 'bottom-right',
                    $width, $height, $wmW, (int) round($wmW * 0.5)
                );
                $opacity = number_format((float) $channel->watermark_opacity, 2, '.', '');

                $inputs .= ' -i ' . escapeshellarg($src);
                $filters[] = "[{$idx}:v]scale={$wmW}:-1:flags=lanczos,format=rgba,colorchannelmixer=aa={$opacity}[wm]";
                $filters[] = "{$next}[wm]overlay={$x}:{$y}[vc2]";
                $next  = '[vc2]';
                $idx++;
            }
        }

        $filters[] = "{$next}format=rgba[cout]";

        return [$inputs, implode(';', $filters)];
    }

    /**
     * Which canvas input style Stage 2 should open.
     *
     *   png    — image2 `-loop 1`: the demuxer re-opens overlay.png by path for
     *            every packet, so a rewrite is picked up by the live encoder
     *            with no restart at all (default).
     *   static — classic `-i overlay.png` with no loop: the canvas is decoded
     *            once and held for the life of Stage 2, so an overlay edit
     *            needs an encoder restart (still never a Stage-1 restart).
     *
     * Misconfiguration degrades to "overlay updates need a restart", never to
     * a broken stream.
     */
    private function resolveCanvasMode(AdminChannel $channel): string
    {
        $mode = strtolower((string) config('playout.canvas_mode', 'png'));

        return $mode === 'static' ? 'static' : 'png';
    }

    /**
     * True when the configured canvas mode actually re-reads the file, so an
     * overlay edit can be applied live. When false the edit is written anyway
     * (so the next restart picks it up) and an encoder restart is requested.
     */
    private function canvasIsLive(AdminChannel $channel): bool
    {
        return $this->resolveCanvasMode($channel) !== 'static';
    }

    private function ramDir(AdminChannel $channel): string
    {
        return "{$this->ramRoot}/{$channel->channel_slug}";
    }

    /**
     * Admission control: refuse to start a new encoder while the box is
     * already saturated, so playout can never starve Flussonic / XC-VM.
     */
    private function loadGateOpen(): bool
    {
        $gate = (float) config('playout.load_gate', 40);

        if ($gate <= 0) {
            return true;
        }

        $load = $this->oneMinuteLoad();

        return $load <= $gate;
    }

    private function oneMinuteLoad(): float
    {
        if (is_readable('/proc/loadavg')) {
            $parts = explode(' ', (string) @file_get_contents('/proc/loadavg'));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                return (float) $parts[0];
            }
        }

        // macOS / non-procfs fallback for local development
        @exec('uptime 2>/dev/null', $out);
        $line = implode(' ', $out);
        if (preg_match('/load average[s]?:\s*([0-9.]+)/i', $line, $m)) {
            return (float) $m[1];
        }

        return 0.0;
    }

    /**
     * Write ticker.txt to /dev/shm/studio/ — the Linux RAM disk.
     * This is the single most impactful I/O optimization for studio playout:
     * FFmpeg's drawtext reload=1 issues a filesystem read on every video
     * frame (25-60 reads/second). On disk this spikes OS queue depth and
     * blocks segment output; on /dev/shm reads are practically free.
     */
    private function writeTickerFile(string $streamDir, AdminChannel $channel): void
    {
        $text = ($channel->enable_ticker && $channel->ticker_text)
            ? $channel->ticker_text
            : '';

        $slug    = $channel->channel_slug ?? basename($streamDir);
        $ramDir  = "{$this->ramRoot}/{$slug}";

        if (! is_dir($ramDir)) {
            @mkdir($ramDir, 0755, true);
        }

        $file = "{$ramDir}/ticker.txt";

        try {
            File::put($file, $text);
        } catch (\Exception $e) {
            Log::warning('Could not write ticker.txt to RAM disk', [
                'path'  => $file,
                'error' => $e->getMessage(),
            ]);
            // Fallback: try the original stream directory
            $fallback = "{$streamDir}/ticker.txt";
            @file_put_contents($fallback, $text);
        }

        // Also write to streamDir as a fallback for any code that references it
        @file_put_contents("{$streamDir}/ticker.txt", $text);
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

        // Only tear down something that is actually alive; the watchdog also
        // calls this when the process is already gone, and killing costs ~2s
        // of grace-period sleeps.
        if ($this->isRunning($channel)) {
            $pid = (int) Cache::get($this->cacheKey($channel));
            $this->killPlayout($pid, $streamDir);
            Cache::forget($this->cacheKey($channel));
        }

        // Refresh assets before rebuilding the scripts
        $this->writeOverlayAssets($streamDir, $channel);

        $playlist = $this->resolvePlaylist($channel);
        $files    = $this->collectFiles($playlist, $channel);
        if (empty($files)) {
            return;
        }

        $loopScript = $this->writePlayoutScript(
            $streamDir, $files, $channel, $this->resolveCanvasMode($channel)
        );
        $pid        = $this->launchScript($loopScript, $streamDir, $channel);

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
     * Resolve the files Stage 1 plays: the prepared intermediates only.
     *
     * There is deliberately no fallback to the raw upload. Stage 1 `-c copy`s
     * whatever it is handed, so one un-normalised file would reintroduce the
     * exact mixed-geometry / mixed-frame-rate / missing-audio failures this
     * architecture exists to remove. Anything that could not be prepared is
     * excluded and counted instead of being smuggled into the concat list.
     */
    private function collectFiles(Collection $playlist, AdminChannel $channel): array
    {
        $files    = [];
        $slug     = $channel->channel_slug;
        $excluded = [];

        foreach ($playlist as $content) {
            if (! $content || ! $content->file_path) {
                continue;
            }

            $absolute = Storage::disk('public')->path($content->file_path);

            if (! File::exists($absolute)) {
                $excluded[] = ['content_id' => $content->id ?? null, 'reason' => 'source missing'];
                Log::warning('My channel content file missing, skipping', [
                    'content_id' => $content->id ?? null,
                    'path'       => $absolute,
                ]);
                continue;
            }

            if ((int) $content->id <= 0) {
                $excluded[] = ['content_id' => $content->id ?? null, 'reason' => 'not a stored record'];
                continue;
            }

            $prepared = $this->preparedPathFor($slug, (int) $content->id);

            if (! is_file($prepared)) {
                $excluded[] = ['content_id' => $content->id, 'title' => $content->title, 'reason' => 'not prepared'];
                Log::warning('My channel content not prepared, excluding from playout', [
                    'channel_id' => $channel->id,
                    'content_id' => $content->id,
                    'expected'   => $prepared,
                ]);
                continue;
            }

            $files[] = $prepared;
        }

        $files = array_values(array_unique($files));

        // A stale path in the middle would make the whole ffmpeg run abort.
        $files = array_values(array_filter($files, fn ($f) => File::exists($f)));

        if ($excluded) {
            Log::warning('My channel content excluded from playout (not prepared)', [
                'channel_id' => $channel->id,
                'excluded'   => $excluded,
            ]);
        }

        return $files;
    }

    /**
     * Build the playout wrapper: one supervisor plus two supervised stages.
     *
     * Stage 1 (PLAYOUT) — `-c copy` through the concat demuxer into a FIFO.
     *   No encoding, so it costs ~0% of a core, and because it emits a single
     *   mpegts stream it is the ONLY place concat boundary timestamps are ever
     *   reconciled.
     * Stage 2 (ENCODE) — reads the FIFO, applies the overlay filtergraph and
     *   writes HLS. It never sees a file boundary, so its filtergraph and
     *   encoder never re-negotiate mid-stream.
     *
     * The supervisor holds the FIFO open RDWR for the whole broadcast. That is
     * the trick that makes the split safe: without that extra descriptor a
     * reader sees EOF the instant the last writer closes (and a writer sees
     * EPIPE the instant the last reader closes), so every stage-2 restart would
     * kill Stage 1 and vice versa. With it, whichever stage dies is simply
     * reopened by its own loop while the other keeps running.
     *
     * Timestamp design: Stage 2 rebuilds the timeline with fps + setpts at the
     * HEAD of its graph — before any overlay — so any PTS discontinuity that
     * survives Stage 1 can never reach the ticker, the clock or the encoder.
     *
     * Playlist edits rewrite concat.txt and signal Stage 1 only; encoder
     * trouble and graph changes are handled by Stage 2 only. Either way the
     * segment number is recomputed from disk, so the HLS playlist continues
     * instead of resetting.
     */
    private function writePlayoutScript(
        string $streamDir,
        array $files,
        AdminChannel $channel,
        string $canvasMode
    ): string {
        $concatPath = "{$streamDir}/concat.txt";

        // -stream_loop -1 makes ffmpeg loop the list forever, so it never
        // reaches EOF and there is no scheduled break in the broadcast. At EOF
        // the demuxer calls avformat_seek_file() back to start_time and adds
        // the file duration to every subsequent packet's PTS, so the loop is
        // seamless in *both* directions: Stage 1 never exits, and the timestamps
        // it writes into the FIFO keep climbing across playlist wraps.
        // (The old 500× repetition of this list was a workaround for exactly
        // this, and made edits slow.)
        File::put(
            $concatPath,
            implode("\n", array_map(fn ($f) => 'file ' . escapeshellarg($f), $files)) . "\n"
        );

        $this->writeStage2Script($streamDir, $channel, $canvasMode);

        $scriptPath = "{$streamDir}/playout.sh";
        $stage2     = "{$streamDir}/stage2.sh";
        $fifo       = "{$streamDir}/playout.pipe";
        $log        = "{$streamDir}/ffmpeg.log";
        $ffmpeg     = $this->ffmpeg;

        $nice = (int) config('playout.nice', 10);
        $loadGate = (int) config('playout.load_gate', 40);

        $script = <<<BASH
#!/bin/bash
set -u

STREAM_DIR="{$streamDir}"
FFMPEG="{$ffmpeg}"
CONCAT="{$concatPath}"
STAGE2="{$stage2}"
FIFO="{$fifo}"
LOG="{$log}"
LOAD_GATE={$loadGate}

log() { echo "\$(date '+%Y-%m-%d %H:%M:%S') \$*" >> "\$LOG"; }

# Highest segment already on disk + 1, so every relaunch appends to the
# existing playlist instead of restarting numbering at zero.
next_segment() {
    last=\$(ls "\$STREAM_DIR"/seg_*.ts 2>/dev/null | sed 's/.*seg_0*//;s/\.ts\$//' | sort -n 2>/dev/null | tail -1)
    if [ -n "\$last" ]; then echo \$((last + 1)); else echo 0; fi
}

load_1min() {
    if [ -r /proc/loadavg ]; then
        cut -d' ' -f1 /proc/loadavg
    else
        echo 0
    fi
}

# Priority: CPU nice + best-effort/idle IO so playout never preempts
# Flussonic, XC-VM or the web request path.
NICE="nice -n {$nice}"
IONICE=""
command -v ionice >/dev/null 2>&1 && IONICE="ionice -c3 -n7"

term() {
    log "SUPERVISOR terminate"
    [ -n "\${S1:-}" ] && kill -TERM "\$S1" 2>/dev/null
    [ -n "\${S2:-}" ] && kill -TERM "\$S2" 2>/dev/null
    pkill -TERM -f "\$FIFO" 2>/dev/null
    exit 143
}
trap 'term' TERM INT HUP

# ── Stage 1: playout, -c copy, never touches the encoder ────────────────────
stage1_loop() {
    while :; do
        T0=\$(date +%s)
        log "STAGE1 start"
        \$NICE \$IONICE "\$FFMPEG" -y -hide_banner -loglevel warning \\
            -fflags +genpts \\
            -re -stream_loop -1 -f concat -safe 0 -i "\$CONCAT" \\
            -map 0:v:0 -map 0:a:0 \\
            -c copy -f mpegts -muxdelay 0 -muxpreload 0 \\
            "\$FIFO" >> "\$LOG" 2>&1
        RC=\$?
        T1=\$(date +%s)
        log "STAGE1 exit rc=\$RC after=\$((T1 - T0))s"
        # Die immediately => missing/broken input: back off so a persistent
        # failure cannot spin a crash loop. A healthy run relaunches at once.
        if [ \$((T1 - T0)) -lt 5 ]; then sleep 10; else sleep 1; fi
    done
}

# ── Stage 2: encoder + overlays + HLS ───────────────────────────────────────
stage2_loop() {
    while :; do
        # Admission control: re-encoding while the box is saturated would
        # starve everything else, so yield instead of dropping the stream
        # into a permanently stalled state.
        L=\$(load_1min)
        if [ "\$LOAD_GATE" -gt 0 ] 2>/dev/null && awk -v l="\$L" -v g="\$LOAD_GATE" 'BEGIN{exit !(l>g)}'; then
            log "STAGE2 deferred load=\$L gate=\$LOAD_GATE"
            sleep 10
            continue
        fi

        N=\$(next_segment)
        T0=\$(date +%s)
        log "STAGE2 start seg=\$N"
        \$NICE \$IONICE bash "\$STAGE2" "\$N" >> "\$LOG" 2>&1 &
        S2=\$!
        echo "\$S2" > "\$STREAM_DIR/stage2.pid"
        wait "\$S2"
        RC=\$?
        rm -f "\$STREAM_DIR/stage2.pid"
        T1=\$(date +%s)
        log "STAGE2 exit rc=\$RC after=\$((T1 - T0))s"
        if [ \$((T1 - T0)) -lt 5 ]; then sleep 10; else sleep 1; fi
    done
}

# Supervisor holds the FIFO open RDWR so neither stage can ever observe EOF.
[ -p "\$FIFO" ] || { rm -f "\$FIFO"; mkfifo -m 666 "\$FIFO" || exit 1; }
exec 3<>"\$FIFO" || { log "FIFO open failed"; exit 1; }
log "SUPERVISOR start fifo=\$FIFO"

stage1_loop &
S1=\$!
stage2_loop &
S2=\$!
echo "\$S1" > "\$STREAM_DIR/stage1.pid"

# If either loop is ever lost, tear the whole unit down; systemd (or the
# watchdog) then relaunches with a fresh script rather than running with a
# half-live pipeline. Polled rather than `wait -n`: `wait -n` falls through to
# a blocking `wait` whenever a child exits non-zero, which would hang here.
while :; do
    kill -0 "\$S1" 2>/dev/null || { log "STAGE1 loop lost"; kill -TERM "\$S2" 2>/dev/null; break; }
    kill -0 "\$S2" 2>/dev/null || { log "STAGE2 loop lost"; kill -TERM "\$S1" 2>/dev/null; break; }
    sleep 1
done
# Nothing that still holds the FIFO may outlive the supervisor.
pkill -TERM -f "\$FIFO" 2>/dev/null
exit 1
BASH;

        File::put($scriptPath, $script);
        chmod($scriptPath, 0755);

        return $scriptPath;
    }

    /**
     * Stage 2 only: the encoder command, written to its own file so a graph
     * change can be picked up by restarting the encoder and nothing else.
     */
    private function writeStage2Script(string $streamDir, AdminChannel $channel, string $canvasMode): void
    {
        $path = "{$streamDir}/stage2.sh";

        $resolution = $channel->output_resolution ?: '1280x720';
        $bitrate    = $channel->output_bitrate ?: 2200;
        $fps        = max(1, (int) ($channel->output_frame_rate ?: 25));
        $height     = $this->evenDimension($this->resolutionHeight($resolution));
        $width      = $this->evenDimension($this->resolutionWidth($resolution));
        $gop        = $fps * 2;

        [$extraInputs, $filterComplex] = $this->buildFiltergraph(
            $streamDir, $channel, $width, $height, $fps, $canvasMode
        );

        $inputLines = $extraInputs !== '' ? "    {$extraInputs} \\\n" : '';

        $videoCodec = (strtolower($channel->transcoding_device ?? 'cpu') === 'gpu' && $this->hasNvenc())
            ? 'h264_nvenc -preset p4 -tune ll -rc vbr -cq 28 -b:v 0'
            : "libx264 -preset ultrafast -tune zerolatency -crf 28 -threads {$this->threadBudget()}";

        $script = <<<BASH
#!/bin/bash
# Stage 2 — encoder + overlays + HLS. Launched by playout.sh with the next
# segment number as \$1 so the playlist always continues from disk.
set -u
STREAM_DIR="{$streamDir}"
FIFO="{$streamDir}/playout.pipe"
N="\${1:-0}"

START_N=""
if [ "\$N" -gt 0 ] 2>/dev/null; then START_N="-start_number \$N"; fi

exec {$this->ffmpeg} -y -hide_banner -loglevel warning \\
    -fflags +discardcorrupt \\
    -f mpegts -i "\$FIFO" \\
    -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=48000 \\
{$inputLines}    -c:v {$videoCodec} \\
    -maxrate {$bitrate}k -bufsize {$bitrate}k -g {$gop} -pix_fmt yuv420p \\
    -vsync cfr \\
    -filter_complex "{$filterComplex}" \\
    -map '[vout]' -map '[aout]' \\
    -c:a aac -b:a 128k -ac 2 -ar 48000 \\
    -f hls -hls_time {$this->segmentDuration} -hls_list_size {$this->playlistSize} \\
    -hls_flags independent_segments+delete_segments+omit_endlist+temp_file+append_list+discont_start \\
    -hls_allow_cache 0 \\
    -hls_segment_type mpegts \\
    -muxdelay 0 -muxpreload 0 \\
    -max_muxing_queue_size 4096 \\
    \$START_N -hls_segment_filename "\$STREAM_DIR/seg_%06d.ts" \\
    "\$STREAM_DIR/index.m3u8"
BASH;

        File::put($path, $script);
        chmod($path, 0755);
    }

    /**
     * Restart Stage 2 only.
     *
     * Stage 1 keeps feeding the FIFO throughout, so the playlist timeline and
     * concat list are untouched and the HLS playlist is never rebuilt from
     * zero — Stage 2 simply recomputes the next segment number from disk and
     * appends with discont_start, exactly like a crash recovery.
     */
    public function restartEncoder(AdminChannel $channel): bool
    {
        $streamDir = $this->streamDir($channel);

        if (! File::isDirectory($streamDir)) {
            return false;
        }

        $this->writeStage2Script($streamDir, $channel, $this->resolveCanvasMode($channel));

        $pid = (int) trim((string) @file_get_contents("{$streamDir}/stage2.pid"));

        if ($pid <= 0) {
            return false;
        }

        @exec("kill -TERM {$pid} 2>/dev/null");

        return true;
    }

    /** Capped so one channel can never monopolise a box shared with Flussonic. */
    private function threadBudget(): int
    {
        $configured = max(1, (int) config('playout.threads', 2));

        $cores = 1;
        if (is_readable('/proc/cpuinfo')) {
            $cores = max(1, substr_count((string) @file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1);
        }

        return max(1, min($configured, (int) max(1, floor($cores / 4))));
    }

    /**
     * Build the Stage 2 -filter_complex graph and its extra -i input lines.
     *
     * Input map (deliberately unchanged from the single-process era so the
     * existing input/audio index conventions keep holding):
     *   0 = mpegts playout FIFO, 1 = anullsrc silence bed, 2 = overlay canvas
     *
     * Normalisation happens at the HEAD of the graph, before anything else.
     * Stage 1 emits one continuous mpegts stream, but a stall, a resync or a
     * restart can still hand Stage 2 a PTS jump — rebuilding the timeline as a
     * strict frame counter first means no discontinuity can ever reach the
     * ticker, the clock or the encoder.
     *
     * The canvas (input 2) is a full-frame RGBA PNG with the logo and watermark
     * already composited, so image/position/size/opacity/enable changes are a
     * file rewrite rather than a restart.
     *
     * Returns [string $extraInputLines, string $filterComplex]
     */
    private function buildFiltergraph(
        string $streamDir,
        AdminChannel $channel,
        int $width,
        int $height,
        int $fps,
        string $canvasMode
    ): array {
        $extraInputs = [];
        $filters     = [];

        $audioInputIndex = 1;
        $canvasInputIndex = 2;

        // ── Head: rebuild the output timeline as a pure frame counter ────────
        $filters[] = "[0:v]fps={$fps},setpts=N/({$fps}*TB)[vnorm]";
        $lastVideo = '[vnorm]';

        // Silence bed is always input 1 so amix always has exactly two inputs.
        $filters[] = "[0:a][{$audioInputIndex}:a]amix=inputs=2:duration=first:dropout_transition=0,aresample=48000:async=1,asetpts=N/SR/TB[aout]";

        // ── Canvas: logo + watermark, already composited onto a full frame ───
        $canvasPath = $this->ramDir($channel) . '/overlay.png';
        $canvasFps  = max(1, (int) config('playout.canvas_fps', 2));

        if ($canvasMode === 'static') {
            // Decoded once and held for the life of Stage 2 (repeatlast).
            $extraInputs[] = '-i ' . escapeshellarg($canvasPath);
        } else {
            // image2 with -loop 1 re-opens the file by path for every packet,
            // so rewriting overlay.png is picked up without any restart.
            $extraInputs[] = sprintf(
                '-f image2 -loop 1 -framerate %d -i %s',
                $canvasFps,
                escapeshellarg($canvasPath)
            );
        }

        $filters[] = "{$lastVideo}[{$canvasInputIndex}:v]overlay=x=0:y=0[vcanvas]";
        $lastVideo = '[vcanvas]';

        // ── Ticker — textfile+reload=1, zero restart on text changes ─────────
        if ($channel->enable_ticker) {
            $slug       = $channel->channel_slug ?? basename($streamDir);
            $tickerFile = "{$this->ramRoot}/{$slug}/ticker.txt";
            $color      = ltrim($channel->ticker_color ?: '#ffffff', '#');
            $bgColor    = $this->hexToFfmpegColor($channel->ticker_background ?: '#000000cc');
            $fontsize   = max(16, (int) round($height * 0.035));
            $barH       = $fontsize + 12;
            $yPos       = $height - $barH;
            $speed      = (int) round(80 + (((float) $channel->ticker_speed - 10) / 90) * 320);
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

        $filters[] = "{$lastVideo}format=yuv420p[vout]";

        return [implode(" \\\n    ", $extraInputs), implode(';', $filters)];
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

    /**
     * Launch the playout supervisor.
     *
     * systemd (production) — a template unit owns the process so CPUQuota,
     * MemoryMax, Nice and IOSchedulingClass are enforced by the kernel, and a
     * crash is restarted by the supervisor rather than by our watchdog.
     * setsid (Docker / dev) — the historical plain launch, with the same
     * limits applied in-process via nice/ionice/-threads.
     */
    private function launchScript(string $scriptPath, string $streamDir, ?AdminChannel $channel = null): ?int
    {
        if ($channel && $this->systemdDriver()) {
            $slug = $this->unitSlug($channel);

            try {
                @exec($this->ctlCommand('start', $slug) . ' 2>&1', $out, $rc);
            } catch (\Throwable $e) {
                // An unsafe slug (or a missing sudo) must never abort a start —
                // fall through to the plain setsid launch below.
                $rc  = 1;
                $out = [$e->getMessage()];
            }

            if ($rc === 0) {
                $pid = $this->unitMainPid($slug);
                if ($pid > 0) {
                    @file_put_contents("{$streamDir}/playout.pid", (string) $pid);
                    return $pid;
                }
            }

            Log::warning('systemd playout start failed, falling back to setsid', [
                'slug'   => $slug,
                'rc'     => $rc,
                'output' => implode(' | ', array_slice($out, -3)),
            ]);
        }

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

    private function systemdDriver(): bool
    {
        $driver = strtolower((string) config('playout.driver', 'auto'));

        if ($driver === 'systemd') {
            return true;
        }
        if ($driver === 'setsid') {
            return false;
        }

        $ctl = (string) config('playout.systemd_ctl');

        // auto: only try systemd when the wrapper is installed AND systemd is
        // actually PID 1 (containers report no /run/systemd/system), so a
        // non-systemd host never pays for a doomed sudo attempt per start.
        return $ctl !== ''
            && is_executable($ctl)
            && is_dir('/run/systemd/system');
    }

    /**
     * Sanitised channel slug — the instance name. Anything that is not a plain
     * slug is rejected outright rather than passed on: the same pattern is
     * enforced again by iptv-playout-ctl before it builds a systemctl argv, so
     * both ends must agree for a start to succeed.
     */
    private function unitSlug(AdminChannel $channel): string
    {
        $slug = (string) $channel->channel_slug;

        if (! preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $slug)) {
            throw new \RuntimeException("Refusing to manage playout for unsafe channel slug: {$slug}");
        }

        return $slug;
    }

    /**
     * All privileged calls go through the validating wrapper under sudo -n.
     *
     * The sudoers rule grants NOPASSWD for the wrapper with no argument list,
     * so the wrapper's own slug allow-list is what keeps the grant narrow —
     * PHP never invokes systemctl directly.
     */
    private function ctlCommand(string $action, string $slug): string
    {
        $ctl = (string) config('playout.systemd_ctl');

        return sprintf('sudo -n %s %s %s', escapeshellarg($ctl), escapeshellarg($action), escapeshellarg($slug));
    }

    private function unitMainPid(string $slug): int
    {
        exec($this->ctlCommand('show', $slug) . ' 2>/dev/null', $out);

        foreach ($out as $line) {
            if (preg_match('/^MainPID=(\d+)$/', trim($line), $m) && (int) $m[1] > 0) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    /** Stop the systemd unit for this channel, if systemd owns it. */
    private function stopUnit(AdminChannel $channel): void
    {
        if (! $this->systemdDriver()) {
            return;
        }

        try {
            $slug = $this->unitSlug($channel);
        } catch (\RuntimeException $e) {
            Log::warning($e->getMessage(), ['channel_id' => $channel->id]);
            return;
        }

        @exec($this->ctlCommand('stop', $slug) . ' 2>&1', $out, $rc);

        if ($rc !== 0) {
            Log::warning('systemd playout stop failed', [
                'slug'   => $slug,
                'rc'     => $rc,
                'output' => implode(' | ', array_slice($out, -3)),
            ]);
        }
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
            File::makeDirectory($path, 0775, true, true);
        } elseif (! is_writable($path)) {
            @chmod($path, 0775);
        }
    }
}