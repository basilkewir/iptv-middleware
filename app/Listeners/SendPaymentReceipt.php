<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentReceipt implements ShouldQueue
{
    public function __construct() {}

    public function handle(object $event): void
    {
        Log::info('Payment receipt sent', [
            'payment_id' => $event->payment->id ?? null,
            'type' => class_basename($event),
        ]);
    }
}
