<?php

namespace App\Listeners;

use App\Events\UserLogin;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateLastLogin implements ShouldQueue
{
    public function __construct() {}

    public function handle(UserLogin $event): void
    {
        $event->user->update(['updated_at' => now()]);
    }
}
