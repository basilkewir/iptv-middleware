<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicenseRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasValidLicense = License::where('status', License::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasValidLicense) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'License required',
                'code' => 'LICENSE_REQUIRED',
            ], 403);
        }

        return redirect()->route('license.required');
    }
}
