<?php

namespace App\Listeners;

use App\Events\StreamStarted;
use App\Models\StreamLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class StreamStartedListener implements ShouldQueue
{
    public function __construct() {}

    public function handle(StreamStarted $event): void
    {
        StreamLog::create([
            'channel_id' => $event->stream->channel_id,
            'user_id' => $event->stream->user_id,
            'status' => 'started',
            'ip_address' => $event->stream->ip_address,
            'user_agent' => $event->stream->user_agent,
            'started_at' => now(),
        ]);

        Log::info('Stream started', [
            'stream_id' => $event->stream->id,
            'channel_id' => $event->stream->channel_id,
            'user_id' => $event->stream->user_id,
        ]);
    }
}
