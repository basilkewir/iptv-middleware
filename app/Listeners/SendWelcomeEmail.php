<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    public function __construct() {}

    public function handle(UserRegistered $event): void
    {
        Log::info('Welcome email sent', [
            'user_id' => $event->user->id,
        ]);
    }
}
