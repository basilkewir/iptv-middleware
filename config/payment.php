<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'default' => env('PAYMENT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', true),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'usd'),
        'api_version' => env('STRIPE_API_VERSION', '2023-10-16'),
        'max_retry_attempts' => env('STRIPE_MAX_RETRY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal Configuration
    |--------------------------------------------------------------------------
    */

    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', false),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currency' => env('PAYPAL_CURRENCY', 'USD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    */

    'methods' => [
        'credit_card' => [
            'enabled' => true,
            'brands' => ['visa', 'mastercard', 'amex', 'discover'],
        ],
        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
        ],
        'bank_transfer' => [
            'enabled' => env('BANK_TRANSFER_ENABLED', false),
            'instructions' => env('BANK_TRANSFER_INSTRUCTIONS', ''),
        ],
        'crypto' => [
            'enabled' => env('CRYPTO_PAYMENT_ENABLED', false),
            'currencies' => ['BTC', 'ETH', 'USDT'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    */

    'plans' => [
        'trial' => [
            'name' => 'Free Trial',
            'duration' => env('TRIAL_DURATION_DAYS', 7),
            'price' => 0,
            'features' => ['basic_channels', 'sd_quality'],
        ],
        'basic' => [
            'name' => 'Basic',
            'price' => env('BASIC_PLAN_PRICE', 9.99),
            'currency' => 'usd',
            'interval' => 'month',
            'features' => ['basic_channels', 'hd_quality', 'epg'],
        ],
        'premium' => [
            'name' => 'Premium',
            'price' => env('PREMIUM_PLAN_PRICE', 19.99),
            'currency' => 'usd',
            'interval' => 'month',
            'features' => ['all_channels', 'fhd_quality', 'epg', 'catchup', 'multi_device'],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => env('ENTERPRISE_PLAN_PRICE', 49.99),
            'currency' => 'usd',
            'interval' => 'month',
            'features' => ['all_channels', 'uhd_quality', 'epg', 'catchup', 'multi_device', 'api_access', 'priority_support'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        'prefix' => env('INVOICE_PREFIX', 'INV-'),
        'starting_number' => env('INVOICE_STARTING_NUMBER', 1000),
        'footer_text' => env('INVOICE_FOOTER', 'Thank you for your business!'),
        'logo_path' => env('INVOICE_LOGO_PATH'),
        'tax_rate' => env('INVOICE_TAX_RATE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Refund Policy
    |--------------------------------------------------------------------------
    */

    'refund' => [
        'enabled' => env('REFUND_ENABLED', true),
        'window_days' => env('REFUND_WINDOW_DAYS', 30),
        'auto_approve' => env('REFUND_AUTO_APPROVE', false),
        'max_refund_percentage' => env('MAX_REFUND_PERCENTAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'enabled' => env('PAYMENT_WEBHOOKS_ENABLED', true),
        'retry_attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
        'timeout' => env('WEBHOOK_TIMEOUT', 30),
    ],

];
