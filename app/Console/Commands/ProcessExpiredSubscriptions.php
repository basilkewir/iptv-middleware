<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Events\SubscriptionExpired;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expired
                            {--grace-period=3 : Days after expiry before deactivation}
                            {--dry-run : Simulate without making changes}';

    protected $description = 'Process expired subscriptions and deactivate them';

    public function handle(): int
    {
        $gracePeriod = (int) $this->option('grace-period');
        $dryRun = $this->option('dry-run');

        $this->info("Processing expired subscriptions (grace period: {$gracePeriod} days)...");

        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('end_date', '<', now()->subDays($gracePeriod))
            ->with(['user', 'subscriptionPackage'])
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            $this->info('No expired subscriptions found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$expiredSubscriptions->count()} expired subscriptions.");

        $processed = 0;
        $notificationsSent = 0;

        foreach ($expiredSubscriptions as $subscription) {
            try {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would expire subscription #{$subscription->id} for user #{$subscription->user_id}");
                    $processed++;
                    continue;
                }

                $this->expireSubscription($subscription);
                $processed++;

                $this->line("  Subscription #{$subscription->id} expired for user #{$subscription->user_id}");
            } catch (\Exception $e) {
                $this->error("  Failed to expire subscription #{$subscription->id}: {$e->getMessage()}");

                Log::error('Subscription expiry failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processing completed. Processed: {$processed}");

        return Command::SUCCESS;
    }

    private function expireSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'expired',
        ]);

        $user = $subscription->user;

        if ($user) {
            $this->notifyUser($user, $subscription);
        }

        event(new SubscriptionExpired($subscription));

        Log::info('Subscription expired', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'expired_at' => $subscription->end_date,
        ]);
    }

    private function notifyUser($user, Subscription $subscription): void
    {
        try {
            $user->notify(new \App\Notifications\SubscriptionExpiredNotification(
                $subscription->subscriptionPackage->name,
                $subscription->end_date
            ));
        } catch (\Exception $e) {
            Log::warning('Failed to send expiry notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
