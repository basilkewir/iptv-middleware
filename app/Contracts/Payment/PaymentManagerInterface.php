<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Models\Invoice;
use App\Enums\Payment\PaymentStatus;

interface PaymentManagerInterface
{
    public function processPayment(User $user, array $paymentData): Payment;

    public function refundPayment(Payment $payment, ?float $amount = null, ?string $reason = null): Payment;

    public function getPaymentHistory(User $user, int $limit = 50): array;

    public function getPaymentStatus(string $paymentId): ?PaymentStatus;

    public function cancelPayment(Payment $payment): void;

    public function retryPayment(Payment $payment): Payment;

    public function getProvider(string $providerName): PaymentProviderInterface;

    public function registerProvider(string $name, PaymentProviderInterface $provider): void;

    public function getAvailableProviders(): array;

    public function generateInvoice(User $user, array $items, array $options = []): Invoice;

    public function processInvoicePayment(Invoice $invoice, array $paymentData): Payment;
}
