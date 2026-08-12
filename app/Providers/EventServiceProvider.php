<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\StreamStarted;
use App\Events\StreamStopped;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Events\PaymentRefunded;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionCancelled;
use App\Events\SubscriptionExpired;
use App\Events\UserRegistered;
use App\Events\UserLogin;
use App\Events\ContentImported;
use App\Events\EPGUpdated;
use App\Listeners\SendStreamNotification;
use App\Listeners\UpdateStreamStats;
use App\Listeners\ProcessPaymentWebhook;
use App\Listeners\SendPaymentReceipt;
use App\Listeners\UpdateInvoiceStatus;
use App\Listeners\NotifySubscriptionExpiry;
use App\Listeners\SendWelcomeEmail;
use App\Listeners\UpdateLastLogin;
use App\Listeners\IndexContent;
use App\Listeners\UpdateEPGCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        StreamStarted::class => [
            SendStreamNotification::class,
            UpdateStreamStats::class,
        ],

        StreamStopped::class => [
            SendStreamNotification::class,
            UpdateStreamStats::class,
        ],

        PaymentCompleted::class => [
            SendPaymentReceipt::class,
            UpdateInvoiceStatus::class,
        ],

        PaymentFailed::class => [
            ProcessPaymentWebhook::class,
        ],

        PaymentRefunded::class => [
            SendPaymentReceipt::class,
            UpdateInvoiceStatus::class,
        ],

        SubscriptionCreated::class => [
            NotifySubscriptionExpiry::class,
        ],

        SubscriptionCancelled::class => [
            NotifySubscriptionExpiry::class,
        ],

        SubscriptionExpired::class => [
            NotifySubscriptionExpiry::class,
        ],

        UserRegistered::class => [
            SendWelcomeEmail::class,
        ],

        UserLogin::class => [
            UpdateLastLogin::class,
        ],

        ContentImported::class => [
            IndexContent::class,
        ],

        EPGUpdated::class => [
            UpdateEPGCache::class,
        ],
    ];

    protected $subscribe = [];

    public function boot(): void
    {
        parent::boot();
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
