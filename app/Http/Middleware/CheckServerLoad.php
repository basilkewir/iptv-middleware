<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\ServerService\ServerManager;
use Symfony\Component\HttpFoundation\Response;

class CheckServerLoad
{
    protected ServerManager $serverManager;

    public function __construct(ServerManager $serverManager)
    {
        $this->serverManager = $serverManager;
    }

    /**
     * Handle an incoming request.
     * Checks if the server has capacity to handle the stream request.
     * Returns 503 Service Unavailable if server is at capacity.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $serverId = $request->route('server')?->id ?? $request->input('server_id');

        if (!$serverId) {
            return $next($request);
        }

        $cacheKey = "server_load:{$serverId}";
        $loadData = Cache::get($cacheKey);

        if (!$loadData) {
            $loadData = $this->serverManager->getCurrentLoad($serverId);
            Cache::put($cacheKey, $loadData, 60);
        }

        if ($loadData['is_at_capacity'] ?? false) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Server is currently at maximum capacity. Please try another server.',
                    'server_id' => $serverId,
                    'load_percentage' => $loadData['load_percentage'] ?? 100,
                ], 503)->withHeaders([
                    'Retry-After' => 30,
                    'X-Server-Load' => $loadData['load_percentage'] ?? 100,
                ]);
            }

            return back()->with('error', 'Server is at capacity. Please try again shortly or select a different server.');
        }

        if (($loadData['load_percentage'] ?? 0) > 80) {
            $request->merge(['server_high_load' => true]);
        }

        $request->merge(['server_load' => $loadData]);

        return $next($request);
    }
}
