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

        if (! $user->is_admin && ! $user->is_reseller) {
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
