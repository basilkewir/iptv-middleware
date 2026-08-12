<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $packageName,
        public $endDate
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Subscription Has Expired')
            ->greeting('Subscription Expired')
            ->line("Your {$this->packageName} subscription has expired on {$this->endDate}.")
            ->line('Please renew your subscription to continue accessing our services.')
            ->action('Renew Subscription', url('/subscription/plans'))
            ->line('If you have any questions, please contact support.');
    }
}
