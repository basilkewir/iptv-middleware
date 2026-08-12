<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Stripe',
                'slug' => 'stripe',
                'description' => 'Pay securely with credit/debit cards via Stripe',
                'icon' => 'credit-card',
                'gateway' => 'stripe',
                'config' => json_encode([
                    'publishable_key' => env('STRIPE_KEY', ''),
                    'secret_key' => env('STRIPE_SECRET', ''),
                ]),
                'supported_currencies' => json_encode(['USD', 'EUR', 'GBP', 'CAD', 'AUD']),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PayPal',
                'slug' => 'paypal',
                'description' => 'Pay with your PayPal account or card',
                'icon' => 'paypal',
                'gateway' => 'paypal',
                'config' => json_encode([
                    'client_id' => env('PAYPAL_CLIENT_ID', ''),
                    'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
                    'mode' => env('PAYPAL_MODE', 'sandbox'),
                ]),
                'supported_currencies' => json_encode(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY']),
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bitcoin',
                'slug' => 'bitcoin',
                'description' => 'Pay with Bitcoin cryptocurrency',
                'icon' => 'bitcoin',
                'gateway' => 'bitcoin',
                'config' => json_encode([
                    'api_key' => env('BITCOIN_API_KEY', ''),
                    'webhook_secret' => env('BITCOIN_WEBHOOK_SECRET', ''),
                ]),
                'supported_currencies' => json_encode(['BTC']),
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('payment_methods')->insert($methods);
    }
}
