<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained module gate for the admin panel.
 *
 * Full-access users (admins / roles with `full_access`) may reach every admin
 * module. Moderators with `my_channels` permission can access VOD management
 * and their assigned channels. Other roles are limited to read-only views.
 */
class AdminModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Full access — everything is open
        if ($user && $user->canManageAllMyChannels()) {
            return $next($request);
        }

        // Moderator / channel_manager with my_channels permission —
        // allow VOD management and channel admin routes
        if ($user && $user->hasPermission('my_channels')) {
            $allowedPrefixes = [
                'admin/channels/admin',
                'admin/vod',
                'admin/dashboard',
            ];
            foreach ($allowedPrefixes as $prefix) {
                if (Str::startsWith($request->path(), $prefix)) {
                    return $next($request);
                }
            }
        }

        // Fallback: only channel admin routes
        if (Str::startsWith($request->path(), 'admin/channels/admin')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to access this module.'], 403);
        }

        return redirect('/admin/channels/admin');
    }
}