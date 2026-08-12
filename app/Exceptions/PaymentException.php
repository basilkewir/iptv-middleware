<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PaymentException extends Exception
{
    protected $message = 'Payment processing error';
    protected $code = 402;

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => [
                'code' => 'PAYMENT_ERROR',
                'message' => $this->message,
            ],
        ], $this->code);
    }
}
