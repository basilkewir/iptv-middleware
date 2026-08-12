<?php

declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\Payment\PaymentProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalProvider implements PaymentProviderInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->clientId = config('payment.paypal.client_id', '');
        $this->clientSecret = config('payment.paypal.client_secret', '');
        $this->baseUrl = config('payment.paypal.sandbox', true)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function charge(float $amount, string $currency, ?string $token = null, array $metadata = []): array
    {
        $this->validateCredentials();

        try {
            $accessToken = $this->getAccessToken();

            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $metadata['reference_id'] ?? uniqid('iptv_'),
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                        'description' => $metadata['description'] ?? 'IPTV Subscription',
                    ],
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'landing_page' => 'BILLING',
                            'user_action' => 'PAY_NOW',
                        ],
                    ],
                ],
            ];

            if ($token) {
                $payload['payment_source']['paypal']['vault_id'] = $token;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post("{$this->baseUrl}/v2/checkout/orders", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('PayPal order created', [
                    'order_id' => $data['id'],
                    'amount' => $amount,
                    'currency' => $currency,
                ]);

                return [
                    'transaction_id' => $data['id'],
                    'status' => $data['status'],
                    'amount' => $amount,
                    'currency' => $currency,
                    'approval_url' => $this->extractApprovalUrl($data),
                    'payer_id' => $data['payer']['payer_id'] ?? null,
                ];
            }

            $error = $response->json('message', 'Unknown error');
            throw new \RuntimeException("PayPal charge failed: {$error}");
        } catch (\Exception $e) {
            Log::error('PayPal charge error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function captureOrder(string $orderId): array
    {
        $this->validateCredentials();

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

            if ($response->successful()) {
                $data = $response->json();

                Log::info('PayPal order captured', ['order_id' => $orderId]);

                return [
                    'transaction_id' => $data['id'],
                    'status' => $data['status'],
                    'capture_id' => $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                    'amount' => $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null,
                    'currency' => $data['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? null,
                ];
            }

            $error = $response->json('message', 'Unknown error');
            throw new \RuntimeException("PayPal capture order failed: {$error}");
        } catch (\Exception $e) {
            Log::error('PayPal capture order error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function refund(string $transactionId, float $amount, ?string $reason = null): array
    {
        $this->validateCredentials();

        try {
            $accessToken = $this->getAccessToken();

            $payload = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => 'USD',
                ],
            ];

            if ($reason) {
                $payload['note_to_payer'] = $reason;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post("{$this->baseUrl}/v2/payments/captures/{$transactionId}/refund", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('PayPal refund successful', [
                    'refund_id' => $data['id'],
                    'capture_id' => $transactionId,
                    'amount' => $amount,
                ]);

                return [
                    'refund_id' => $data['id'],
                    'status' => $data['status'],
                    'amount' => $data['amount']['value'],
                    'currency' => $data['amount']['currency_code'],
                    'created_at' => $data['create_time'],
                ];
            }

            $error = $response->json('message', 'Unknown error');
            throw new \RuntimeException("PayPal refund failed: {$error}");
        } catch (\Exception $e) {
            Log::error('PayPal refund error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getTransactionDetails(string $transactionId): array
    {
        $this->validateCredentials();

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/v2/checkout/orders/{$transactionId}");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'id' => $data['id'],
                    'status' => $data['status'],
                    'intent' => $data['intent'],
                    'purchase_units' => $data['purchase_units'],
                    'payer' => $data['payer'] ?? null,
                    'create_time' => $data['create_time'],
                    'update_time' => $data['update_time'],
                ];
            }

            $error = $response->json('message', 'Unknown error');
            throw new \RuntimeException("PayPal get transaction failed: {$error}");
        } catch (\Exception $e) {
            Log::error('PayPal get transaction error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function createCustomer(string $email, string $name, array $metadata = []): array
    {
        $this->validateCredentials();

        try {
            $accessToken = $this->getAccessToken();

            $payload = [
                'email_address' => $email,
                'name' => [
                    'given_name' => explode(' ', $name)[0] ?? $name,
                    'surname' => explode(' ', $name)[1] ?? '',
                ],
                'metadata' => $metadata,
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/v1/customer/customer-profiles", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('PayPal customer created', ['customer_id' => $data['id']]);

                return [
                    'customer_id' => $data['id'],
                    'email' => $email,
                    'name' => $name,
                ];
            }

            $error = $response->json('message', 'Unknown error');
            throw new \RuntimeException("PayPal create customer failed: {$error}");
        } catch (\Exception $e) {
            Log::error('PayPal create customer error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getProviderName(): string
    {
        return PaymentProvider::PAYPAL;
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Accept-Language' => 'en_US',
        ])
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->accessToken = $data['access_token'];
            return $this->accessToken;
        }

        throw new \RuntimeException('Failed to obtain PayPal access token');
    }

    public function createSubscription(string $customerId, string $planId, array $metadata = []): array
    {
        $this->validateCredentials();

        try {
            $accessToken = $this->getAccessToken();

            $payload = [
                'plan_id' => $planId,
                'subscriber' => [
                    'payer_id' => $customerId,
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en_US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ])->post("{$this->baseUrl}/v1/billing/subscriptions", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('PayPal subscription created', ['subscription_id' => $data['id']]);

                return [
                    'subscription_id' => $data['id'],
                    'status' => $data['status'],
                    'approval_url' => $this->extractSubscriptionApprovalUrl($data),
                ];
            }

            $error = $response->json('message', 'Unknown error');
            throw new \RuntimeException("PayPal create subscription failed: {$error}");
        } catch (\Exception $e) {
            Log::error('PayPal create subscription error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancelSubscription(string $subscriptionId, string $reason = ''): array
    {
        $this->validateCredentials();

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/cancel", [
                'reason' => $reason,
            ]);

            if ($response->successful()) {
                Log::info('PayPal subscription cancelled', ['subscription_id' => $subscriptionId]);

                return [
                    'subscription_id' => $subscriptionId,
                    'status' => 'cancelled',
                ];
            }

            $error = $response->json('message', 'Unknown error');
            throw new \RuntimeException("PayPal cancel subscription failed: {$error}");
        } catch (\Exception $e) {
            Log::error('PayPal cancel subscription error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function extractApprovalUrl(array $data): ?string
    {
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        return null;
    }

    private function extractSubscriptionApprovalUrl(array $data): ?string
    {
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        return null;
    }

    private function validateCredentials(): void
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \RuntimeException('PayPal credentials are not configured.');
        }
    }
}
