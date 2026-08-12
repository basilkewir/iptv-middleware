<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifySubscription
{
    /**
     * Handle an incoming request.
     * Checks if the authenticated user has an active subscription.
     * Some routes (like subscription management) should exclude this middleware.
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

        if ($user->is_admin) {
            return $next($request);
        }

        if (!$user->hasActiveSubscription()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Active subscription required.',
                    'redirect' => route('subscription.plans'),
                ], 403);
            }
            return redirect()->route('subscription.plans')
                ->with('error', 'You need an active subscription to access this content.');
        }

        if ($user->hasActiveSubscription()) {
            $activeSub = $user->activeSubscription();
            if ($activeSub && $activeSub->end_date && $activeSub->end_date->diffInDays(now()) <= 3) {
                $request->merge(['subscription_expiring_soon' => true]);
            }
        }

        return $next($request);
    }
}
