<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

interface PaymentProviderInterface
{
    public function charge(float $amount, string $currency, ?string $token = null, array $metadata = []): array;

    public function refund(string $transactionId, float $amount, ?string $reason = null): array;

    public function getTransactionDetails(string $transactionId): array;

    public function createCustomer(string $email, string $name, array $metadata = []): array;

    public function getProviderName(): string;

    public function isConfigured(): bool;
}
