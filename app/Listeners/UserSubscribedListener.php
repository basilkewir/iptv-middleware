<?php

namespace App\Listeners;

use App\Events\UserSubscribed;
use App\Jobs\SendSubscriptionReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UserSubscribedListener implements ShouldQueue
{
    public function __construct() {}

    public function handle(UserSubscribed $event): void
    {
        $subscription = $event->subscription;
        $user = $subscription->user;

        Log::info('User subscribed', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'plan' => $subscription->subscriptionPackage->name,
        ]);

        SendSubscriptionReminder::dispatch($subscription->id, 3)
            ->delay($subscription->end_date->subDays(3));

        SendSubscriptionReminder::dispatch($subscription->id, 1)
            ->delay($subscription->end_date->subDay());
    }
}
