<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\StreamingService\StreamManager;
use App\Enums\Stream\StreamStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StreamHealthCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    // Seconds without a new HLS segment before we consider the stream stalled
    private const STALL_THRESHOLD_SECONDS = 20;

    public function __construct(
        public int $channelId
    ) {}

    public function handle(StreamManager $streamManager): void
    {
        $channel = Channel::find($this->channelId);

        if (! $channel || ! $channel->is_active) {
            return;
        }

        $stream = $streamManager->getActiveStreamForChannel($this->channelId);

        if (! $stream || $stream->status !== StreamStatus::ACTIVE) {
            return;
        }

        if ($this->isStalled($stream)) {
            Log::warning('Stream stalled — restarting', [
                'stream_id' => $stream->id,
                'channel_id' => $this->channelId,
            ]);

            try {
                $streamManager->restartStream($stream);
            } catch (\Exception $e) {
                Log::error('Failed to restart stalled stream', [
                    'stream_id' => $stream->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function isStalled(Stream $stream): bool
    {
        $segmentDir = storage_path("app/streams/hls/{$stream->id}");

        if (! is_dir($segmentDir)) {
            return true;
        }

        $latest = 0;

        foreach (glob("{$segmentDir}/*.ts") as $file) {
            $mtime = filemtime($file);
            if ($mtime > $latest) {
                $latest = $mtime;
            }
        }

        // No segments at all, or newest segment is too old
        return $latest === 0 || (time() - $latest) > self::STALL_THRESHOLD_SECONDS;
    }
}
