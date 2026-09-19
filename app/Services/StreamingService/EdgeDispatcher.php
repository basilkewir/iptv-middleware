<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Selects the least-loaded active edge server and returns a redirect URL.
 * Falls back to local ingest when no edge servers are configured.
 *
 * Edge servers are rows in streaming_servers with is_active=true.
 * Each edge exposes GET /edge/ping returning {"connections":N,"capacity":N}.
 */
class EdgeDispatcher
{
    private const LOAD_TTL   = 10;  // seconds to cache edge load stats
    private const PROBE_TIMEOUT = 2; // seconds for health probe

    /**
     * Return the best edge base URL for this stream, or null to use local.
     * Format: "http://edge-ip:port"
     */
    public function bestEdge(): ?string
    {
        $servers = Cache::remember('edge:servers', 30, fn () =>
            Server::where('is_active', true)->get()
        );

        if ($servers->isEmpty()) {
            return null;
        }

        $best     = null;
        $bestLoad = PHP_INT_MAX;

        foreach ($servers as $server) {
            $load = $this->edgeLoad($server);
            if ($load !== null && $load < $bestLoad) {
                $bestLoad = $load;
                $best     = $server;
            }
        }

        if ($best === null) {
            return null;
        }

        $proto = $best->protocol ?? 'http';
        $port  = $best->port ?? 80;

        return "{$proto}://{$best->host}:{$port}";
    }

    /**
     * Increment the in-memory connection counter for the chosen edge.
     * Called after a redirect is issued so load stays accurate between probes.
     */
    public function incrementConnections(string $edgeBase): void
    {
        $key = 'edge:conn:' . md5($edgeBase);
        Cache::increment($key);
        Cache::put($key . ':ttl', true, 3600); // keep key alive
    }

    public function decrementConnections(string $edgeBase): void
    {
        $key = 'edge:conn:' . md5($edgeBase);
        $val = (int) Cache::get($key, 0);
        if ($val > 0) {
            Cache::decrement($key);
        }
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Returns load as a fraction 0–1 (connections / capacity).
     * Uses a lightweight /edge/ping probe; falls back to cached value.
     * Returns null if the edge is unreachable.
     */
    private function edgeLoad(Server $server): ?float
    {
        $cacheKey = "edge:load:{$server->id}";

        return Cache::remember($cacheKey, self::LOAD_TTL, function () use ($server) {
            $proto = $server->protocol ?? 'http';
            $url   = "{$proto}://{$server->host}:{$server->port}/edge/ping";

            try {
                $resp = Http::timeout(self::PROBE_TIMEOUT)->get($url);
                if ($resp->successful()) {
                    $data = $resp->json();
                    $cap  = max(1, (int) ($data['capacity'] ?? $server->max_connections ?? 1000));
                    $conn = (int) ($data['connections'] ?? 0);
                    return $conn / $cap;
                }
            } catch (\Throwable) {
                // unreachable
            }

            return null; // mark as unavailable
        });
    }
}
