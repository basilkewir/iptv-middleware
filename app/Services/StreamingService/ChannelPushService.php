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

    public function startPush(Channel $channel, PushDestination $destination): ChannelPushDestination
    {
        $sourceUrl = $channel->active_source_url;

        if (empty($sourceUrl)) {
            throw new \RuntimeException('Channel has no active source URL.');
        }

        $existing = ChannelPushDestination::where('channel_id', $channel->id)
            ->where('push_destination_id', $destination->id)
            ->first();

        if ($existing && $existing->isPushing()) {
            return $existing;
        }

        $outputUrl = $this->buildOutputUrl($destination);
        $command = $this->buildFFmpegCommand($sourceUrl, $outputUrl, $destination->protocol);
        $pid = $this->executeFFmpeg($command, $channel->id, $destination->id);

        $record = $existing ?? new ChannelPushDestination([
            'channel_id' => $channel->id,
            'push_destination_id' => $destination->id,
        ]);

        $record->update([
            'status' => 'pushing',
            'ffmpeg_pid' => $pid,
            'started_at' => now(),
            'stopped_at' => null,
            'last_error' => null,
        ]);

        Log::info('Channel push started', [
            'channel_id' => $channel->id,
            'destination_id' => $destination->id,
            'source' => $sourceUrl,
            'output' => $outputUrl,
            'pid' => $pid,
        ]);

        return $record->fresh();
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
                'started_at' => $push->started_at?->toISOString(),
                'pid' => $push->ffmpeg_pid,
            ])
            ->toArray();
    }

    public function buildOutputUrl(PushDestination $destination): string
    {
        return $destination->full_url;
    }

    public function buildFFmpegCommand(string $inputUrl, string $outputUrl, string $protocol): string
    {
        $inputFlag = str_starts_with($inputUrl, 'rtmp://') ? '-i' : '-re -i';

        if ($protocol === 'srt') {
            return sprintf(
                '%s %s %s -c:v copy -c:a aac -f mpegts "%s" 2>&1',
                $this->ffmpegPath,
                $inputFlag,
                escapeshellarg($inputUrl),
                $outputUrl
            );
        }

        return sprintf(
            '%s %s %s -c:v copy -c:a aac -f flv "%s" 2>&1',
            $this->ffmpegPath,
            $inputFlag,
            escapeshellarg($inputUrl),
            $outputUrl
        );
    }

    private function executeFFmpeg(string $command, int $channelId, int $destinationId): int
    {
        $processKey = $this->cacheKey($channelId, $destinationId);
        exec("{$command} > /dev/null 2>&1 & echo $!", $output);

        if (empty($output)) {
            throw new \RuntimeException('Failed to start FFmpeg process.');
        }

        $pid = (int) end($output);
        Cache::put($processKey, $pid, 86400);

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
