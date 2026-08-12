<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Contracts\Streaming\StreamManagerInterface;
use App\Enums\Stream\StreamStatus;
use App\Events\StreamStarted;
use App\Events\StreamStopped;
use App\Models\Stream;
use App\Models\Server;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StreamManager implements StreamManagerInterface
{
    private HLSStreamer $hlsStreamer;
    private RTMPStreamer $rtmpStreamer;
    private LoadBalancer $loadBalancer;

    private const STREAM_CACHE_TTL = 3600;
    private const CONNECTION_CACHE_TTL = 300;

    public function __construct(
        HLSStreamer $hlsStreamer,
        RTMPStreamer $rtmpStreamer,
        LoadBalancer $loadBalancer
    ) {
        $this->hlsStreamer = $hlsStreamer;
        $this->rtmpStreamer = $rtmpStreamer;
        $this->loadBalancer = $loadBalancer;
    }

    public function startStream(Channel $channel): Stream
    {
        $existingStream = $this->getActiveStreamForChannel($channel->id);

        if ($existingStream && $existingStream->status === StreamStatus::ACTIVE) {
            return $existingStream;
        }

        $server = $this->loadBalancer->selectServer($channel);

        if (!$server) {
            throw new \RuntimeException('No available servers for streaming.');
        }

        $stream = Stream::create([
            'id' => Str::uuid()->toString(),
            'channel_id' => $channel->id,
            'server_id' => $server->id,
            'stream_key' => $this->generateStreamKey($channel),
            'status' => StreamStatus::STARTING,
            'started_at' => now(),
            'codec' => $channel->codec ?? 'h264',
            'resolution' => $channel->resolution ?? '1920x1080',
            'bitrate' => $channel->bitrate ?? 5000,
        ]);

        try {
            $this->initiateStream($stream, $channel, $server);

            $stream->update(['status' => StreamStatus::ACTIVE]);

            Cache::put(
                "stream:channel:{$channel->id}",
                $stream->id,
                self::STREAM_CACHE_TTL
            );

            Cache::put(
                "stream:server:{$server->id}:count",
                $this->getServerStreamCount($server->id) + 1,
                self::CONNECTION_CACHE_TTL
            );

            event(new StreamStarted($stream));

            Log::info('Stream started', [
                'stream_id' => $stream->id,
                'channel_id' => $channel->id,
                'server_id' => $server->id,
            ]);

            return $stream;
        } catch (\Exception $e) {
            $stream->update(['status' => StreamStatus::FAILED]);

            Log::error('Failed to start stream', [
                'stream_id' => $stream->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function stopStream(Stream $stream): void
    {
        $stream->update([
            'status' => StreamStatus::STOPPING,
            'stopped_at' => now(),
        ]);

        try {
            $this->terminateStream($stream);

            Cache::forget("stream:channel:{$stream->channel_id}");

            $currentCount = $this->getServerStreamCount($stream->server_id);
            Cache::put(
                "stream:server:{$stream->server_id}:count",
                max(0, $currentCount - 1),
                self::CONNECTION_CACHE_TTL
            );

            $stream->update(['status' => StreamStatus::STOPPED]);

            event(new StreamStopped($stream));

            Log::info('Stream stopped', [
                'stream_id' => $stream->id,
                'channel_id' => $stream->channel_id,
            ]);
        } catch (\Exception $e) {
            $stream->update(['status' => StreamStatus::FAILED]);

            Log::error('Failed to stop stream', [
                'stream_id' => $stream->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getActiveStreamForChannel(int $channelId): ?Stream
    {
        $streamId = Cache::get("stream:channel:{$channelId}");

        if ($streamId) {
            return Stream::find($streamId);
        }

        return Stream::where('channel_id', $channelId)
            ->where('status', StreamStatus::ACTIVE)
            ->first();
    }

    public function getStreamConnections(Stream $stream): int
    {
        return (int) Cache::get(
            "stream:connections:{$stream->id}",
            fn () => $stream->connections()->count()
        );
    }

    public function addConnection(Stream $stream): void
    {
        $key = "stream:connections:{$stream->id}";
        Cache::increment($key);

        $stream->increment('current_viewers');

        $this->updateServerLoad($stream->server_id);
    }

    public function removeConnection(Stream $stream): void
    {
        $key = "stream:connections:{$stream->id}";
        $count = Cache::decrement($key);

        if ($count <= 0) {
            Cache::forget($key);
        }

        $stream->decrement('current_viewers');

        $this->updateServerLoad($stream->server_id);
    }

    public function getServerStreamCount(int $serverId): int
    {
        return (int) Cache::get(
            "stream:server:{$serverId}:count",
            fn () => Stream::where('server_id', $serverId)
                ->where('status', StreamStatus::ACTIVE)
                ->count()
        );
    }

    public function getStreamUrl(Stream $stream): string
    {
        $server = $stream->server;

        return match ($stream->protocol) {
            'hls' => "{$server->stream_url}/hls/{$stream->stream_key}.m3u8",
            'rtmp' => "rtmp://{$server->ip}:1935/live/{$stream->stream_key}",
            default => "{$server->stream_url}/live/{$stream->stream_key}",
        };
    }

    public function validateStreamKey(string $streamKey): bool
    {
        return Cache::remember(
            "stream:valid_key:{$streamKey}",
            self::STREAM_CACHE_TTL,
            fn () => Stream::where('stream_key', $streamKey)->exists()
        );
    }

    public function getStreamsByServer(int $serverId): array
    {
        return Stream::where('server_id', $serverId)
            ->where('status', StreamStatus::ACTIVE)
            ->get()
            ->toArray();
    }

    public function restartStream(Stream $stream): Stream
    {
        $channel = $stream->channel;
        $this->stopStream($stream);

        return $this->startStream($channel);
    }

    public function getStreamStats(Stream $stream): array
    {
        return [
            'id' => $stream->id,
            'status' => $stream->status->value,
            'connections' => $this->getStreamConnections($stream),
            'uptime' => $stream->started_at
                ? now()->diffInSeconds($stream->started_at)
                : 0,
            'url' => $this->getStreamUrl($stream),
            'bitrate' => $stream->bitrate,
            'resolution' => $stream->resolution,
            'codec' => $stream->codec,
        ];
    }

    private function initiateStream(Stream $stream, Channel $channel, Server $server): void
    {
        match ($stream->protocol ?? 'hls') {
            'hls' => $this->hlsStreamer->startStream($stream, $channel, $server),
            'rtmp' => $this->rtmpStreamer->startStream($stream, $channel, $server),
            default => $this->hlsStreamer->startStream($stream, $channel, $server),
        };
    }

    private function terminateStream(Stream $stream): void
    {
        match ($stream->protocol ?? 'hls') {
            'hls' => $this->hlsStreamer->stopStream($stream),
            'rtmp' => $this->rtmpStreamer->stopStream($stream),
            default => $this->hlsStreamer->stopStream($stream),
        };
    }

    private function generateStreamKey(Channel $channel): string
    {
        return Str::lower(Str::random(8)) . '.' . $channel->id;
    }

    private function updateServerLoad(int $serverId): void
    {
        $activeStreams = Stream::where('server_id', $serverId)
            ->where('status', StreamStatus::ACTIVE)
            ->sum('current_viewers');

        Cache::put(
            "server:load:{$serverId}",
            $activeStreams,
            self::CONNECTION_CACHE_TTL
        );
    }
}
