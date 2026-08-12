<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSubscriptionReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 3;

    public function __construct(
        public int $subscriptionId,
        public int $daysBeforeExpiry
    ) {}

    public function handle(): void
    {
        $subscription = Subscription::with('user', 'subscriptionPackage')->find($this->subscriptionId);

        if (! $subscription || $subscription->status !== 'active') {
            return;
        }

        $expiresAt = $subscription->end_date;
        $reminderDate = now()->addDays($this->daysBeforeExpiry);

        if ($expiresAt->greaterThan($reminderDate)) {
            return;
        }

        $user = $subscription->user;

        if (! $user || ! $user->email) {
            Log::warning('Cannot send reminder: user not found or no email', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        $user->notify(new SubscriptionReminderNotification(
            subscription: $subscription,
            daysRemaining: $this->daysBeforeExpiry
        ));

        Log::info('Subscription reminder sent', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'days_remaining' => $this->daysBeforeExpiry,
        ]);
    }
}
