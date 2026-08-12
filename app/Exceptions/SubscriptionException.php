<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SubscriptionException extends Exception
{
    protected $message = 'Subscription error';
    protected $code = 403;

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => [
                'code' => 'SUBSCRIPTION_ERROR',
                'message' => $this->message,
            ],
        ], $this->code);
    }
}
