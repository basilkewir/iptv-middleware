<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResellerMiddleware
{
    /**
     * Handle an incoming request.
     * Checks if the authenticated user has reseller privileges.
     * Resellers can manage their own sub-users and subscriptions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user->is_reseller && !$user->is_admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Reseller access required.'], 403);
            }
            abort(403, 'Unauthorized. Reseller access required.');
        }

        if ($user->is_reseller && $user->reseller && !$user->reseller->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your reseller account is inactive.'], 403);
            }
            abort(403, 'Your reseller account has been deactivated. Please contact support.');
        }

        return $next($request);
    }
}
