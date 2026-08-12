<?php

namespace App\Listeners;

use App\Events\ContentImported;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class IndexContent implements ShouldQueue
{
    public function __construct() {}

    public function handle(ContentImported $event): void
    {
        Log::info('Content indexed', [
            'count' => $event->count,
        ]);
    }
}
