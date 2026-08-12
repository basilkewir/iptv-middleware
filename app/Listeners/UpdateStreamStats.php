<?php

namespace App\Listeners;

use App\Events\StreamStarted;
use App\Events\StreamStopped;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateStreamStats implements ShouldQueue
{
    public function __construct() {}

    public function handle(object $event): void
    {
        Log::info('Stream stats updated', [
            'user_id' => $event->userId ?? null,
            'channel_id' => $event->channelId ?? null,
            'type' => class_basename($event),
        ]);
    }
}
