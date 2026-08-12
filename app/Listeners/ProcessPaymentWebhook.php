<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    public function __construct() {}

    public function handle(PaymentFailed $event): void
    {
        Log::warning('Payment failed webhook', [
            'user_id' => $event->userId,
            'reason' => $event->reason,
        ]);
    }
}
