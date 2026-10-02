<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Symfony\Component\HttpFoundation\Cookie;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Per-application XSRF cookie name.
     *
     * The framework hard-codes 'XSRF-TOKEN', and cookies are scoped by
     * hostname — not by port. Two Laravel apps on the same host therefore
     * overwrite each other's token on every response, and whichever app you
     * used second leaves the first one failing every write with 419
     * "CSRF token mismatch". That is exactly what the middleware (:8081) and
     * the HMS (:80) did to each other on 192.168.20.15.
     *
     * A distinct name per app keeps them independent.
     */
    public const XSRF_COOKIE = 'iptvmiddleware_xsrf';

    /**
     * Create the app-scoped XSRF cookie.
     *
     * Identical to the parent implementation except for the cookie name, so
     * the value is still the plain session token the frontend reads.
     */
    protected function newCookie($request, $config)
    {
        return new Cookie(
            static::XSRF_COOKIE,
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }
}
