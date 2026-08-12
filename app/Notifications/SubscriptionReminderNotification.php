<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $packageName = $this->subscription->subscriptionPackage->name ?? 'Unknown';
        $endDate = $this->subscription->end_date->format('Y-m-d');

        return (new MailMessage)
            ->subject('Subscription Expiring Soon')
            ->greeting('Subscription Reminder')
            ->line("Your {$packageName} subscription will expire in {$this->daysRemaining} day(s) on {$endDate}.")
            ->line('Please renew your subscription to avoid interruption of service.')
            ->action('Renew Subscription', url('/subscription/plans'))
            ->line('If you have any questions, please contact support.');
    }
}
