<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();

        // A user is granted admin-panel access either through the legacy
        // boolean flags or by holding a role that implies management duties
        // (any role other than the plain `client` role). Holding a role does
        // not grant full rights — channel access is still scoped per user.
        if (! $user->is_admin && ! $user->is_reseller && ! $user->hasAdminPanelAccess()) {
            Auth::logout();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Admin or reseller access required.'], 403);
            }
            return redirect()->route('login')->withErrors([
                'general' => 'Access restricted to administrators and resellers only.',
            ]);
        }

        return $next($request);
    }
}
