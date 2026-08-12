<?php

declare(strict_types=1);

namespace App\Services\AdminChannel;

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelBroadcast;
use App\Models\AdminChannel\MyChannelPlaylist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MyChannelHlsService
{
    private string $segmentRoot;
    private string $ffmpeg;
    private int $segmentDuration = 6;
    private int $playlistSize    = 12;

    public function __construct()
    {
        $this->segmentRoot = storage_path('app/streams/hls');
        $this->ffmpeg      = config('streaming.transcoding.ffmpeg_path', '/usr/bin/ffmpeg');
    }

    public function start(MyChannelBroadcast $broadcast): bool
    {
        $channel = $broadcast->channel;

        if (! $channel) {
            return false;
        }

        $this->stop($channel);

        $streamDir = $this->streamDir($channel);
        $this->ensureDirectory($streamDir);

        $playlist = $this->resolvePlaylist($channel);

        if ($playlist->isEmpty()) {
            $broadcast->update(['status' => 'error', 'error_message' => 'No playable content in playlist']);
            Log::warning('My channel broadcast started with empty playlist', ['channel_id' => $channel->id]);
            return false;
        }

        try {
            $files = $this->collectFiles($playlist);
            if (empty($files)) {
                throw new \RuntimeException('No playable media files found on disk');
            }

            $this->writeOverlayAssets($streamDir, $channel);
            $loopScript = $this->writeLoopScript($streamDir, $files, $channel);
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
        $pid = Cache::get($this->cacheKey($channel));

        if ($pid) {
            @exec("kill -TERM -{$pid} 2>/dev/null");
            @exec("kill -TERM {$pid} 2>/dev/null");
            Cache::forget($this->cacheKey($channel));
            Log::info('Playout terminated', ['channel_id' => $channel->id, 'pid' => $pid]);
        }

        $streamDir = $this->streamDir($channel);
        @exec("pkill -f " . escapeshellarg($streamDir) . " 2>/dev/null");

        if (File::isDirectory($streamDir)) {
            File::deleteDirectory($streamDir);
        }
    }

    public function isRunning(AdminChannel $channel): bool
    {
        return (bool) Cache::get($this->cacheKey($channel));
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

    // ─── Private helpers ──────────────────────────────────────────────────────

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
    private function restartKeepingSegments(AdminChannel $channel): void
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
        $pid = Cache::get($this->cacheKey($channel));
        if ($pid) {
            @exec("kill -TERM -{$pid} 2>/dev/null");
            @exec("kill -TERM {$pid} 2>/dev/null");
            Cache::forget($this->cacheKey($channel));
        }
        @exec("pkill -f " . escapeshellarg($streamDir) . " 2>/dev/null");
        usleep(800000); // 0.8 s — let FFmpeg flush final segment

        // Refresh image assets with new files before rebuilding script
        $this->writeOverlayAssets($streamDir, $channel);

        $playlist = $this->resolvePlaylist($channel);
        $files    = $this->collectFiles($playlist);
        if (empty($files)) {
            return;
        }

        $loopScript = $this->writeLoopScript($streamDir, $files, $channel, $nextSegment);
        $pid        = $this->launchScript($loopScript, $streamDir);

        if ($pid) {
            Cache::put($this->cacheKey($channel), $pid, 86400);
        }
    }

    private function resolvePlaylist(AdminChannel $channel): \Illuminate\Support\Collection
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

    private function collectFiles(\Illuminate\Support\Collection $playlist): array
    {
        $files = [];

        foreach ($playlist as $content) {
            if (! $content || ! $content->file_path) {
                continue;
            }

            $absolute = Storage::disk('public')->path($content->file_path);

            if (! File::exists($absolute)) {
                Log::warning('My channel content file missing, skipping', [
                    'content_id' => $content->id,
                    'path'       => $absolute,
                ]);
                continue;
            }

            $files[] = $absolute;
        }

        return $files;
    }

    /**
     * Build the playout shell script.
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
        $concatLines = array_map(fn ($f) => 'file ' . escapeshellarg($f), $files);
        File::put($concatPath, implode("\n", $concatLines) . "\n");

        [$extraInputs, $filterComplex, $hasImages] = $this->buildFiltergraph(
            $streamDir, $channel, $width, $height, $fps
        );

        $inputLines  = $hasImages ? $extraInputs . "\\\n    " : '';
        $startNum    = $startSegment > 0 ? "-start_number {$startSegment} " : '';

        $script = <<<BASH
#!/bin/sh
STREAM_DIR="{$streamDir}"
FFMPEG="{$ffmpeg}"
CONCAT="{$concatPath}"

exec "\$FFMPEG" -y -hide_banner -loglevel warning \\
    -stream_loop -1 -f concat -safe 0 -re -i "\$CONCAT" \\
    {$inputLines}-c:v libx264 -preset veryfast -tune zerolatency -crf 23 \\
    -maxrate {$bitrate}k -bufsize {$bitrate}k -r {$fps} -g {$gop} -pix_fmt yuv420p \\
    -filter_complex "{$filterComplex}" \\
    -map '[vout]' -map 0:a? \\
    -c:a aac -b:a 128k -ac 2 -ar 48000 \\
    -f hls -hls_time {$this->segmentDuration} -hls_list_size {$this->playlistSize} \\
    -hls_flags independent_segments+delete_segments+append_list \\
    -hls_allow_cache 0 \\
    -hls_segment_type mpegts \\
    {$startNum}-hls_segment_filename "\$STREAM_DIR/segment_%05d.ts" \\
    "\$STREAM_DIR/index.m3u8" >> "\$STREAM_DIR/ffmpeg.log" 2>&1
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
     *
     * Returns [string $extraInputLines, string $filterComplex, bool $hasImages]
     */
    private function buildFiltergraph(string $streamDir, AdminChannel $channel, int $width, int $height, int $fps): array
    {
        $extraInputs = [];
        $filters     = [];
        $lastVideo   = '[vscaled]';
        $inputIndex  = 1; // input 0 is the concat source

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

        // Rename final label to [vout]
        $last      = array_pop($filters);
        $filters[] = preg_replace('/\[[a-z_]+\]$/', '[vout]', $last);

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

    private function ensureDirectory(string $path): void
    {
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }
}
