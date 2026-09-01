<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Inertia;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'errors' => fn () => $request->hasSession() && $request->session()->has('errors')
                ? (object) $request->session()->get('errors')->getBag('default')->getMessages()
                : (object) [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id'              => $request->user()->id,
                    'username'        => $request->user()->username,
                    'email'           => $request->user()->email,
                    'first_name'      => $request->user()->first_name,
                    'last_name'       => $request->user()->last_name,
                    'role'            => $request->user()->roleName(),
                    'role_label'      => $request->user()->roleLabel(),
                    'is_admin'        => $request->user()->is_admin,
                    'is_reseller'     => $request->user()->is_reseller,
                    'can_manage_all'  => $request->user()->canManageAllMyChannels(),
                    'permissions'     => $request->user()->permissionsList(),
                ] : null,
            ],
        ]);

        return $next($request);
    }
}
