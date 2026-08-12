<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Events\SubscriptionCancelled;
use App\Events\SubscriptionExpired;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifySubscriptionExpiry implements ShouldQueue
{
    public function __construct() {}

    public function handle(object $event): void
    {
        $subscription = $event->subscription;

        Log::info('Subscription notification', [
            'subscription_id' => $subscription->id ?? null,
            'user_id' => $subscription->user_id ?? null,
            'type' => class_basename($event),
        ]);
    }
}
