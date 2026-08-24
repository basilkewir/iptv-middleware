<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Contracts\Streaming\RTMPStreamerInterface;
use App\Models\Stream;
use App\Models\Channel;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RTMPStreamer implements RTMPStreamerInterface
{
    private string $rtmpHost;
    private int $rtmpPort;
    private string $rtmpSecret;

    public function __construct()
    {
        $this->rtmpHost = config('streaming.rtmp.host', '127.0.0.1');
        $this->rtmpPort = (int) config('streaming.rtmp.port', 1935);
        $this->rtmpSecret = config('streaming.rtmp.secret', '');
    }

    public function startStream(Stream $stream, Channel $channel, Server $server): void
    {
        $inputUrl = $channel->source_url;
        $outputUrl = $this->buildOutputUrl($stream);

        Log::info('Starting RTMP stream', [
            'stream_id' => $stream->id,
            'input' => $inputUrl,
            'output' => $outputUrl,
        ]);

        $ffmpegCommand = $this->buildFFmpegCommand($inputUrl, $outputUrl, $stream);

        $this->executeFFmpeg($ffmpegCommand, $stream->id);

        $this->registerStream($stream);
    }

    public function stopStream(Stream $stream): void
    {
        $processKey = "rtmp:ffmpeg:{$stream->id}";
        $pid = cache()->get($processKey);

        if ($pid) {
            exec("kill -TERM {$pid} 2>/dev/null");
            cache()->forget($processKey);
            Log::info('RTMP FFmpeg process terminated', ['stream_id' => $stream->id, 'pid' => $pid]);
        }

        $this->unregisterStream($stream);
    }

    public function getStreamUrl(Stream $stream): string
    {
        return "rtmp://{$this->rtmpHost}:{$this->rtmpPort}/live/{$stream->stream_key}";
    }

    public function getPlayUrl(Stream $stream): string
    {
        return "rtmp://{$this->rtmpHost}:{$this->rtmpPort}/live/{$stream->stream_key}";
    }

    public function isStreamActive(Stream $stream): bool
    {
        $activeStreams = Cache::get('rtmp:active_streams', []);

        return isset($activeStreams[$stream->id]);
    }

    public function getActiveStreams(): array
    {
        return Cache::get('rtmp:active_streams', []);
    }

    public function publishStream(Stream $stream, string $streamKey): bool
    {
        $valid = $this->validateStreamKey($streamKey);

        if (!$valid) {
            Log::warning('Invalid RTMP stream key', ['stream_key' => $streamKey]);
            return false;
        }

        $this->registerStream($stream);

        return true;
    }

    public function unpublishStream(Stream $stream): void
    {
        $this->unregisterStream($stream);
    }

    public function onPublish(string $streamKey, string $clientIp): void
    {
        Log::info('RTMP publish event', [
            'stream_key' => $streamKey,
            'client_ip' => $clientIp,
        ]);

        $stream = Stream::where('stream_key', $streamKey)->first();

        if ($stream) {
            $this->registerStream($stream);
        }
    }

    public function onUnpublish(string $streamKey, string $clientIp): void
    {
        Log::info('RTMP unpublish event', [
            'stream_key' => $streamKey,
            'client_ip' => $clientIp,
        ]);

        $stream = Stream::where('stream_key', $streamKey)->first();

        if ($stream) {
            $this->unregisterStream($stream);
        }
    }

    public function getStreamStats(Stream $stream): array
    {
        $stats = cache()->get("rtmp:stats:{$stream->id}", []);

        return array_merge($stats, [
            'stream_id' => $stream->id,
            'url' => $this->getStreamUrl($stream),
            'is_active' => $this->isStreamActive($stream),
        ]);
    }

    public function validateStreamKey(string $streamKey): bool
    {
        return Stream::where('stream_key', $streamKey)->exists();
    }

    public function getServerStats(): array
    {
        $statsUrl = "http://{$this->rtmpHost}:8080/stat";

        try {
            $response = Http::timeout(5)->get($statsUrl);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch RTMP server stats', ['error' => $e->getMessage()]);
        }

        return [
            'nginx_rtmp' => [
                'stat' => 'unavailable',
            ],
        ];
    }

    private function buildOutputUrl(Stream $stream): string
    {
        return "rtmp://{$this->rtmpHost}:{$this->rtmpPort}/live/{$stream->stream_key}";
    }

    private function buildFFmpegCommand(string $input, string $output, Stream $stream): string
    {
        return sprintf(
            'ffmpeg -i %s -c:v copy -c:a aac -f flv %s 2>&1',
            escapeshellarg($input),
            escapeshellarg($output)
        );
    }

    private function executeFFmpeg(string $command, string $streamId): void
    {
        $processKey = "rtmp:ffmpeg:{$streamId}";

        exec("{$command} > /dev/null 2>&1 & echo $!", $output);

        if (!empty($output)) {
            $pid = end($output);
            cache()->put($processKey, $pid, 86400);

            Log::info('RTMP FFmpeg process started', [
                'stream_id' => $streamId,
                'pid' => $pid,
            ]);
        }
    }

    private function registerStream(Stream $stream): void
    {
        $activeStreams = Cache::get('rtmp:active_streams', []);
        $activeStreams[$stream->id] = [
            'stream_key' => $stream->stream_key,
            'started_at' => now()->toISOString(),
            'server_id' => $stream->server_id,
        ];
        Cache::put('rtmp:active_streams', $activeStreams, 86400);
    }

    private function unregisterStream(Stream $stream): void
    {
        $activeStreams = Cache::get('rtmp:active_streams', []);
        unset($activeStreams[$stream->id]);
        Cache::put('rtmp:active_streams', $activeStreams, 86400);
    }
}
