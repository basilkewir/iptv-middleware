<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class StreamingException extends Exception
{
    protected $message = 'Streaming error';
    protected $code = 503;

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => [
                'code' => 'STREAMING_ERROR',
                'message' => $this->message,
            ],
        ], $this->code);
    }
}
