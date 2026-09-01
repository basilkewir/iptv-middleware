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
 * module. Every other panel user (moderator, reseller, support, ...) is limited
 * to the My Channels module, i.e. `/admin/channels/admin*`, where visibility is
 * further restricted to the channels assigned to them.
 */
class AdminModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->canManageAllMyChannels()) {
            return $next($request);
        }

        if (Str::startsWith($request->path(), 'admin/channels/admin')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to access this module.'], 403);
        }

        return redirect('/admin/channels/admin');
    }
}