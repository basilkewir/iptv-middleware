<?php

namespace App\Listeners;

use App\Events\EPGUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateEPGCache implements ShouldQueue
{
    public function __construct() {}

    public function handle(EPGUpdated $event): void
    {
        Log::info('EPG cache updated', [
            'channel_count' => $event->channelCount,
        ]);
    }
}
