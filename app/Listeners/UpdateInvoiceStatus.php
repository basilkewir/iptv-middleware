<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateInvoiceStatus implements ShouldQueue
{
    public function __construct() {}

    public function handle(object $event): void
    {
        Log::info('Invoice status updated', [
            'payment_id' => $event->payment->id ?? null,
            'type' => class_basename($event),
        ]);
    }
}
