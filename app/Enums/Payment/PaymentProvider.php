<?php

namespace App\Enums\Payment;

enum PaymentProvider: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case CRYPTO = 'crypto';
    case BANK_TRANSFER = 'bank_transfer';
}
