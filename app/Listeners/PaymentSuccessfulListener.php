<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Jobs\SendSubscriptionReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PaymentSuccessfulListener implements ShouldQueue
{
    public function __construct() {}

    public function handle(PaymentSuccessful $event): void
    {
        $payment = $event->payment;

        Log::info('Payment successful', [
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);

        if ($payment->subscription) {
            SendSubscriptionReminder::dispatch($payment->subscription->id, 3)
                ->delay($payment->subscription->end_date->subDays(3));
        }
    }
}
