<?php

declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\Payment\PaymentProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeProvider implements PaymentProviderInterface
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('payment.stripe.secret_key') ?? '';
        $this->baseUrl = 'https://api.stripe.com/v1';
    }

    public function charge(float $amount, string $currency, ?string $token = null, array $metadata = []): array
    {
        $this->validateCredentials();

        $amountInCents = (int) round($amount * 100);

        $payload = [
            'amount' => $amountInCents,
            'currency' => strtolower($currency),
            'description' => $metadata['description'] ?? 'IPTV Subscription',
            'metadata' => array_filter($metadata, function ($value) {
                return $value !== null && is_string($value);
            }),
        ];

        if ($token) {
            $payload['source'] = $token;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post("{$this->baseUrl}/charges", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Stripe charge successful', [
                    'charge_id' => $data['id'],
                    'amount' => $amount,
                    'currency' => $currency,
                ]);

                return [
                    'transaction_id' => $data['id'],
                    'status' => $data['status'],
                    'amount' => $data['amount'] / 100,
                    'currency' => $data['currency'],
                    'created' => $data['created'],
                    'receipt_url' => $data['receipt_url'] ?? null,
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe charge failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe charge error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function refund(string $transactionId, float $amount, ?string $reason = null): array
    {
        $this->validateCredentials();

        $amountInCents = (int) round($amount * 100);

        $payload = [
            'charge' => $transactionId,
            'amount' => $amountInCents,
        ];

        if ($reason) {
            $payload['reason'] = $reason;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post("{$this->baseUrl}/refunds", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Stripe refund successful', [
                    'refund_id' => $data['id'],
                    'charge_id' => $transactionId,
                    'amount' => $amount,
                ]);

                return [
                    'refund_id' => $data['id'],
                    'status' => $data['status'],
                    'amount' => $data['amount'] / 100,
                    'currency' => $data['currency'],
                    'created' => $data['created'],
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe refund failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe refund error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getTransactionDetails(string $transactionId): array
    {
        $this->validateCredentials();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
            ])->get("{$this->baseUrl}/charges/{$transactionId}");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'id' => $data['id'],
                    'status' => $data['status'],
                    'amount' => $data['amount'] / 100,
                    'currency' => $data['currency'],
                    'created' => $data['created'],
                    'paid' => $data['paid'],
                    'refunded' => $data['refunded'],
                    'amount_refunded' => $data['amount_refunded'] / 100,
                    'description' => $data['description'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe get transaction failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe get transaction error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function createCustomer(string $email, string $name, array $metadata = []): array
    {
        $this->validateCredentials();

        $payload = [
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post("{$this->baseUrl}/customers", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Stripe customer created', ['customer_id' => $data['id']]);

                return [
                    'customer_id' => $data['id'],
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'created' => $data['created'],
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe create customer failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe create customer error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function createSubscription(string $customerId, string $priceId, array $metadata = []): array
    {
        $this->validateCredentials();

        $payload = [
            'customer' => $customerId,
            'items' => [
                ['price' => $priceId],
            ],
            'metadata' => $metadata,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post("{$this->baseUrl}/subscriptions", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Stripe subscription created', ['subscription_id' => $data['id']]);

                return [
                    'subscription_id' => $data['id'],
                    'status' => $data['status'],
                    'current_period_start' => $data['current_period_start'],
                    'current_period_end' => $data['current_period_end'],
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe create subscription failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe create subscription error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $this->validateCredentials();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
            ])->delete("{$this->baseUrl}/subscriptions/{$subscriptionId}");

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Stripe subscription cancelled', ['subscription_id' => $subscriptionId]);

                return [
                    'subscription_id' => $data['id'],
                    'status' => $data['status'],
                    'canceled_at' => $data['canceled_at'],
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe cancel subscription failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe cancel subscription error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function createInvoiceItem(string $customerId, float $amount, string $currency, array $metadata = []): array
    {
        $this->validateCredentials();

        $amountInCents = (int) round($amount * 100);

        $payload = [
            'customer' => $customerId,
            'amount' => $amountInCents,
            'currency' => strtolower($currency),
            'metadata' => $metadata,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post("{$this->baseUrl}/invoiceitems", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'invoice_item_id' => $data['id'],
                    'amount' => $data['amount'] / 100,
                    'currency' => $data['currency'],
                    'created' => $data['created'],
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe create invoice item failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe create invoice item error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function finalizeInvoice(string $invoiceId): array
    {
        $this->validateCredentials();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
            ])->post("{$this->baseUrl}/invoices/{$invoiceId}/finalize");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'invoice_id' => $data['id'],
                    'status' => $data['status'],
                    'total' => $data['total'] / 100,
                    'currency' => $data['currency'],
                ];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);
            throw new \RuntimeException("Stripe finalize invoice failed: {$error['message']}");
        } catch (\Exception $e) {
            Log::error('Stripe finalize invoice error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getProviderName(): string
    {
        return PaymentProvider::STRIPE;
    }

    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    private function validateCredentials(): void
    {
        if (empty($this->secretKey)) {
            throw new \RuntimeException('Stripe API key is not configured.');
        }
    }
}
