<?php

declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Contracts\Payment\PaymentManagerInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Events\PaymentRefunded;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PaymentManager implements PaymentManagerInterface
{
    private array $providers = [];

    public function __construct()
    {
        $this->registerProviders();
    }

    public function processPayment(User $user, array $paymentData): Payment
    {
        $providerName = $paymentData['provider'] ?? PaymentProvider::STRIPE;
        $provider = $this->getProvider($providerName);

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'USD',
            'provider' => $providerName,
            'status' => PaymentStatus::PENDING,
            'description' => $paymentData['description'] ?? null,
            'metadata' => $paymentData['metadata'] ?? null,
            'invoice_id' => $paymentData['invoice_id'] ?? null,
        ]);

        try {
            $result = $provider->charge(
                amount: $paymentData['amount'],
                currency: $paymentData['currency'] ?? 'USD',
                token: $paymentData['token'] ?? null,
                metadata: array_merge(
                    $paymentData['metadata'] ?? [],
                    ['payment_id' => $payment->id, 'user_id' => $user->id]
                )
            );

            $payment->update([
                'status' => PaymentStatus::COMPLETED,
                'transaction_id' => $result['transaction_id'] ?? null,
                'provider_response' => $result,
                'completed_at' => now(),
            ]);

            if ($payment->invoice_id) {
                $this->updateInvoiceStatus($payment->invoice_id, PaymentStatus::COMPLETED);
            }

            event(new PaymentCompleted($payment));

            Log::info('Payment processed successfully', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'provider' => $providerName,
            ]);

            return $payment;
        } catch (\Exception $e) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            if ($payment->invoice_id) {
                $this->updateInvoiceStatus($payment->invoice_id, PaymentStatus::FAILED);
            }

            event(new PaymentFailed($payment, $e));

            Log::error('Payment failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function refundPayment(Payment $payment, ?float $amount = null, ?string $reason = null): Payment
    {
        $provider = $this->getProvider($payment->provider);

        $refundAmount = $amount ?? $payment->amount;

        try {
            $result = $provider->refund(
                transactionId: $payment->transaction_id,
                amount: $refundAmount,
                reason: $reason
            );

            $refund = Payment::create([
                'user_id' => $payment->user_id,
                'amount' => -$refundAmount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'status' => PaymentStatus::REFUNDED,
                'description' => "Refund for payment #{$payment->id}",
                'transaction_id' => $result['refund_id'] ?? null,
                'parent_payment_id' => $payment->id,
                'provider_response' => $result,
                'refunded_at' => now(),
            ]);

            $payment->update([
                'status' => PaymentStatus::REFUNDED,
                'refunded_amount' => $refundAmount,
                'refunded_at' => now(),
            ]);

            if ($payment->invoice_id) {
                $this->updateInvoiceStatus($payment->invoice_id, PaymentStatus::REFUNDED);
            }

            event(new PaymentRefunded($payment, $refund));

            Log::info('Payment refunded', [
                'payment_id' => $payment->id,
                'refund_amount' => $refundAmount,
                'reason' => $reason,
            ]);

            return $refund;
        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getPaymentHistory(User $user, int $limit = 50): array
    {
        return Payment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getPaymentStatus(string $paymentId): ?PaymentStatus
    {
        $payment = Payment::find($paymentId);

        return $payment?->status;
    }

    public function cancelPayment(Payment $payment): void
    {
        $payment->update([
            'status' => PaymentStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);

        Log::info('Payment cancelled', ['payment_id' => $payment->id]);
    }

    public function retryPayment(Payment $payment): Payment
    {
        $user = $payment->user;

        return $this->processPayment($user, [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'description' => $payment->description,
            'metadata' => $payment->metadata,
            'invoice_id' => $payment->invoice_id,
        ]);
    }

    public function getProvider(string $providerName): PaymentProviderInterface
    {
        if (!isset($this->providers[$providerName])) {
            throw new \InvalidArgumentException("Payment provider '{$providerName}' not found.");
        }

        return $this->providers[$providerName];
    }

    public function registerProvider(string $name, PaymentProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    public function generateInvoice(User $user, array $items, array $options = []): Invoice
    {
        $total = 0;

        foreach ($items as $item) {
            $total += $item['amount'] * ($item['quantity'] ?? 1);
        }

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'total' => $total,
            'currency' => $options['currency'] ?? 'USD',
            'status' => 'pending',
            'items' => $items,
            'due_date' => $options['due_date'] ?? now()->addDays(30),
            'metadata' => $options['metadata'] ?? null,
        ]);

        Log::info('Invoice generated', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total' => $total,
        ]);

        return $invoice;
    }

    public function processInvoicePayment(Invoice $invoice, array $paymentData): Payment
    {
        $user = $invoice->user;

        $payment = $this->processPayment($user, [
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'provider' => $paymentData['provider'] ?? PaymentProvider::STRIPE,
            'token' => $paymentData['token'] ?? null,
            'description' => "Payment for invoice #{$invoice->invoice_number}",
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ],
            'invoice_id' => $invoice->id,
        ]);

        return $payment;
    }

    private function registerProviders(): void
    {
        if (config('payment.stripe.enabled', false)) {
            $this->providers[PaymentProvider::STRIPE->value] = app(StripeProvider::class);
        }

        if (config('payment.paypal.enabled', false)) {
            $this->providers[PaymentProvider::PAYPAL->value] = app(PayPalProvider::class);
        }
    }

    private function updateInvoiceStatus(string $invoiceId, PaymentStatus $status): void
    {
        $invoice = Invoice::find($invoiceId);

        if ($invoice) {
            $mappedStatus = match ($status) {
                PaymentStatus::COMPLETED => 'paid',
                PaymentStatus::FAILED => 'failed',
                PaymentStatus::REFUNDED => 'refunded',
                default => 'pending',
            };

            $invoice->update(['status' => $mappedStatus]);
        }
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = config('payment.invoice_prefix', 'INV');
        $timestamp = now()->format('Ymd');
        $sequence = Invoice::whereDate('created_at', now())->count() + 1;

        return "{$prefix}-{$timestamp}-" . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
