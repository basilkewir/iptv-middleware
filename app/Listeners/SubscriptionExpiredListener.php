<?php

namespace App\Listeners;

use App\Events\SubscriptionExpired;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SubscriptionExpiredListener implements ShouldQueue
{
    public function __construct() {}

    public function handle(SubscriptionExpired $event): void
    {
        $subscription = $event->subscription;
        $user = $subscription->user;

        Log::info('Subscription expired', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        if ($user) {
            $user->update(['is_active' => false]);
        }
    }
}
