<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Contracts\Streaming\HLSStreamerInterface;
use App\Models\Stream;
use App\Models\Channel;
use App\Models\Server;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HLSStreamer implements HLSStreamerInterface
{
    private string $segmentDirectory;
    private int $segmentDuration = 6;
    private int $playlistSize = 10;

    public function __construct()
    {
        $this->segmentDirectory = storage_path('app/streams/hls');
    }

    public function startStream(Stream $stream, Channel $channel, Server $server): void
    {
        $streamPath = $this->getStreamPath($stream);

        $this->ensureDirectoryExists($streamPath);

        $inputUrl = $channel->source_url;

        if ($channel->isYouTube() && empty($inputUrl)) {
            $ytService = new \App\Services\YouTubeService();
            $resolveResult = $ytService->resolveToStreamUrl($channel);
            if ($resolveResult['success']) {
                $inputUrl = $resolveResult['stream_url'];
            }
        }

        if (empty($inputUrl)) {
            Log::error('Cannot start HLS stream: no source URL', [
                'stream_id' => $stream->id,
                'channel_id' => $channel->id,
            ]);
            return;
        }

        $ffmpegCommand = $this->buildFFmpegCommand(
            input: $inputUrl,
            outputPath: $streamPath,
            stream: $stream
        );

        Log::info('Starting HLS stream', [
            'stream_id' => $stream->id,
            'command' => $ffmpegCommand,
        ]);

        $this->executeFFmpeg($ffmpegCommand, $stream->id);

        $this->createMasterPlaylist($stream, $channel);
    }

    public function stopStream(Stream $stream): void
    {
        $processKey = "ffmpeg:{$stream->id}";

        $pid = cache()->get($processKey);

        if ($pid) {
            exec("kill -TERM {$pid} 2>/dev/null");
            cache()->forget($processKey);
            Log::info('FFmpeg process terminated', ['stream_id' => $stream->id, 'pid' => $pid]);
        }

        $this->cleanupStream($stream);
    }

    public function getStreamPath(Stream $stream): string
    {
        return "{$this->segmentDirectory}/{$stream->id}";
    }

    public function getSegmentUrl(Stream $stream, string $segmentName): string
    {
        return "/hls/{$stream->id}/{$segmentName}";
    }

    public function getPlaylistUrl(Stream $stream): string
    {
        return "/hls/{$stream->id}/playlist.m3u8";
    }

    public function createMasterPlaylist(Stream $stream, Channel $channel): void
    {
        $streamPath = $this->getStreamPath($stream);

        $resolutions = [
            '1920x1080' => ['bandwidth' => 5000000, 'resolution' => '1920x1080'],
            '1280x720' => ['bandwidth' => 3000000, 'resolution' => '1280x720'],
            '854x480' => ['bandwidth' => 1500000, 'resolution' => '854x480'],
            '640x360' => ['bandwidth' => 800000, 'resolution' => '640x360'],
        ];

        $content = "#EXTM3U\n";
        $content .= "#EXT-X-VERSION:3\n";

        foreach ($resolutions as $variant => $config) {
            $content .= "#EXT-X-STREAM-INF:BANDWIDTH={$config['bandwidth']},RESOLUTION={$config['resolution']}\n";
            $content .= "playlist_{$variant}.m3u8\n";
        }

        File::put("{$streamPath}/master.m3u8", $content);
    }

    public function getActiveSegments(Stream $stream): array
    {
        $streamPath = $this->getStreamPath($stream);

        if (!File::isDirectory($streamPath)) {
            return [];
        }

        $files = File::files($streamPath);

        return array_filter($files, function ($file) {
            return Str::endsWith($file->getFilename(), '.ts');
        });
    }

    public function cleanExpiredSegments(Stream $stream, int $maxAge = 300): int
    {
        $streamPath = $this->getStreamPath($stream);
        $cleaned = 0;

        if (!File::isDirectory($streamPath)) {
            return 0;
        }

        $files = File::files($streamPath);

        foreach ($files as $file) {
            if (
                Str::endsWith($file->getFilename(), '.ts') &&
                $file->getMTime() < (time() - $maxAge)
            ) {
                File::delete($file->getPathname());
                $cleaned++;
            }
        }

        return $cleaned;
    }

    public function updatePlaylist(Stream $stream): void
    {
        $streamPath = $this->getStreamPath($stream);
        $playlistFile = "{$streamPath}/playlist.m3u8";

        $segments = collect(File::files($streamPath))
            ->filter(fn ($file) => Str::endsWith($file->getFilename(), '.ts'))
            ->sortBy(fn ($file) => $file->getMTime())
            ->take(-$this->playlistSize);

        $content = "#EXTM3U\n";
        $content .= "#EXT-X-VERSION:3\n";
        $content .= "#EXT-X-TARGETDURATION:{$this->segmentDuration}\n";
        $content .= "#EXT-X-MEDIA-SEQUENCE:" . $this->calculateMediaSequence($segments) . "\n";

        foreach ($segments as $segment) {
            $duration = $this->segmentDuration;
            $content .= "#EXTINF:{$duration},\n";
            $content .= $segment->getFilename() . "\n";
        }

        File::put($playlistFile, $content);
    }

    public function validateStream(string $streamKey): bool
    {
        $streamPath = "{$this->segmentDirectory}/{$streamKey}";

        return File::isDirectory($streamPath) && File::exists("{$streamPath}/playlist.m3u8");
    }

    private function buildFFmpegCommand(string $input, string $outputPath, Stream $stream): string
    {
        $segmentTime = $this->segmentDuration;

        return sprintf(
            'ffmpeg -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 -i %s ' .
            '-c:v h264_nvenc -preset p4 -tune ll -rc vbr -cq 28 -b:v 0 -maxrate 4000k -bufsize 8000k -c:a aac -f hls ' .
            '-hls_time %d -hls_list_size %d ' .
            '-hls_flags delete_segments+append_list ' .
            '-hls_segment_filename %s/segment_%%03d.ts ' .
            '%s/playlist.m3u8 2>&1',
            escapeshellarg($input),
            $segmentTime,
            $this->playlistSize,
            escapeshellarg($outputPath),
            escapeshellarg($outputPath)
        );
    }

    private function executeFFmpeg(string $command, string $streamId): void
    {
        $processKey = "ffmpeg:{$streamId}";

        $pid = null;

        exec("{$command} > /dev/null 2>&1 & echo $!", $output);

        if (!empty($output)) {
            $pid = end($output);
            cache()->put($processKey, $pid, 86400);

            Log::info('FFmpeg process started', [
                'stream_id' => $streamId,
                'pid' => $pid,
            ]);
        }
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }

    private function cleanupStream(Stream $stream): void
    {
        $streamPath = $this->getStreamPath($stream);

        if (File::isDirectory($streamPath)) {
            File::deleteDirectory($streamPath);
            Log::info('Stream directory cleaned', ['stream_id' => $stream->id]);
        }
    }

    private function calculateMediaSequence($segments): int
    {
        if ($segments->isEmpty()) {
            return 0;
        }

        $firstSegment = $segments->first();

        if (preg_match('/segment_(\d+)\.ts/', $firstSegment->getFilename(), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
