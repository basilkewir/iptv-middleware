<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as Limiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimiter
{
    /**
     * Handle an incoming request.
     * Applies rate limiting based on configurable limits per route.
     *
     * Supported middleware parameters:
     * - RateLimiter:api (60 requests per minute)
     * - RateLimiter:stream (30 requests per minute)
     * - RateLimiter:auth (10 requests per minute)
     * - RateLimiter:strict (5 requests per minute)
     */
    public function handle(Request $request, Closure $next, string $limiter = 'api'): Response
    {
        $limits = [
            'api'     => ['maxAttempts' => 60, 'decayMinutes' => 1],
            'stream'  => ['maxAttempts' => 30, 'decayMinutes' => 1],
            'auth'    => ['maxAttempts' => 10, 'decayMinutes' => 1],
            'strict'  => ['maxAttempts' => 5,  'decayMinutes' => 1],
            'upload'  => ['maxAttempts' => 20, 'decayMinutes' => 1],
        ];

        $config = $limits[$limiter] ?? $limits['api'];

        $key = $this->resolveRateLimitKey($request, $limiter);

        if (Limiter::tooManyAttempts($key, $config['maxAttempts'])) {
            $retryAfter = Limiter::availableIn($key);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => $retryAfter,
                ], 429)->withHeaders([
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => $config['maxAttempts'],
                    'X-RateLimit-Remaining' => 0,
                ]);
            }

            return redirect()->back()->with('error', 'Too many requests. Please wait a moment.');
        }

        Limiter::hit($key, $config['decayMinutes'] * 60);

        $response = $next($request);

        $remaining = $config['maxAttempts'] - Limiter::attempts($key);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $config['maxAttempts'],
            'X-RateLimit-Remaining' => max(0, $remaining),
        ]);
    }

    /**
     * Resolve the rate limit key based on request context.
     */
    protected function resolveRateLimitKey(Request $request, string $limiter): string
    {
        $identifier = $request->user()?->id ?? $request->ip();

        return "{$limiter}:{$identifier}";
    }
}
