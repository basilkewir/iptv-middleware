<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class AuthenticationException extends Exception
{
    protected $message = 'Authentication failed';
    protected $code = 401;

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => [
                'code' => 'AUTHENTICATION_FAILED',
                'message' => $this->message,
            ],
        ], $this->code);
    }
}
