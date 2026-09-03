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

        if ($existing && $existing->isPushing()) {
            return $existing;
        }

        $outputUrl = $this->buildOutputUrl($destination, $streamKey);
        $command = $this->buildFFmpegCommand($sourceUrl, $outputUrl, $destination->protocol, $videoBitrate, $audioBitrate);
        $pid = $this->executeFFmpeg($command, $channel->id, $destination->id);

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
            exec("kill -TERM {$pid} 2>/dev/null");
            Cache::forget($this->cacheKey($push->channel_id, $push->push_destination_id));
            Log::info('Channel push stopped', ['push_id' => $push->id, 'pid' => $pid]);
        }

        $push->update([
            'status' => 'idle',
            'ffmpeg_pid' => null,
            'stopped_at' => now(),
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
        $key = $this->cacheKey($channelId, $destinationId);
        $pid = Cache::get($key);

        if ($pid && ! $this->processExists($pid)) {
            Cache::forget($key);

            return false;
        }

        return (bool) $pid;
    }

    public function getActivePushes(): array
    {
        return ChannelPushDestination::with(['channel', 'pushDestination'])
            ->where('status', 'pushing')
            ->get()
            ->map(fn (ChannelPushDestination $push) => [
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
            ])
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

        $videoCodec = $videoKbps
            ? "-c:v libx264 -b:v {$videoKbps} -preset veryfast -profile:v main"
            : '-c:v copy';
        $audioCodec = $audioKbps ? "-c:a aac -b:a {$audioKbps}" : '-c:a aac -b:a 128k';

        $inputOpts = [];
        if (str_starts_with($inputUrl, 'http://') || str_starts_with($inputUrl, 'https://')) {
            $inputOpts[] = '-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 -reconnect_on_network_error 1';
        } elseif (str_starts_with($inputUrl, 'rtsp://')) {
            $inputOpts[] = '-rtsp_transport tcp -stimeout 10000000';
        }

        $outputOpts = '-flush_packets 1 -max_muxing_queue_size 1024';

        $format = $protocol === 'srt' ? 'mpegts' : 'flv';

        $parts = [$this->ffmpegPath];
        if ($inputOpts) $parts[] = implode(' ', $inputOpts);
        $parts[] = '-i ' . escapeshellarg($inputUrl);
        $parts[] = $videoCodec;
        $parts[] = $audioCodec;
        $parts[] = $outputOpts;
        $parts[] = '-f ' . $format;
        $parts[] = '"' . $outputUrl . '"';

        return implode(' ', $parts);
    }

    private function executeFFmpeg(string $command, int $channelId, int $destinationId): int
    {
        $processKey = $this->cacheKey($channelId, $destinationId);
        $logFile = storage_path("logs/push_{$channelId}_{$destinationId}.log");
        $fullCommand = "{$command} > " . escapeshellarg($logFile) . " 2>&1 & echo $!";

        exec($fullCommand, $output);

        if (empty($output)) {
            throw new \RuntimeException('Failed to start FFmpeg process.');
        }

        $pid = (int) end($output);
        Cache::put($processKey, $pid, 86400);

        usleep(500000);
        if (!$this->processExists($pid)) {
            $log = @file_get_contents($logFile);
            throw new \RuntimeException('FFmpeg exited immediately. Log: ' . substr($log ?? 'empty', 0, 500));
        }

        Log::info('FFmpeg started', [
            'command' => $command,
            'pid' => $pid,
            'log_file' => $logFile,
        ]);

        return $pid;
    }

    private function cacheKey(int $channelId, int $destinationId): string
    {
        return "push:ffmpeg:{$channelId}:{$destinationId}";
    }

    private function processExists(int $pid): bool
    {
        return file_exists("/proc/{$pid}") || exec("kill -0 {$pid} 2>/dev/null") === '';
    }
}
