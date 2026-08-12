<?php

namespace App\Services\ServerService;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;

class ServerManager
{
    private const CACHE_TTL = 60;

    public function getCurrentLoad(int $serverId): array
    {
        $cacheKey = "server:load:{$serverId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($serverId) {
            $server = Server::find($serverId);

            if (!$server) {
                return [
                    'is_at_capacity' => true,
                    'load_percentage' => 100,
                ];
            }

            $currentConnections = $server->current_connections;
            $maxConnections = $server->max_connections;

            $loadPercentage = $maxConnections > 0
                ? round(($currentConnections / $maxConnections) * 100, 2)
                : 0;

            return [
                'is_at_capacity' => $loadPercentage >= 90,
                'load_percentage' => $loadPercentage,
                'current_connections' => $currentConnections,
                'max_connections' => $maxConnections,
            ];
        });
    }

    public function isServerHealthy(Server $server): bool
    {
        return $server->status === 'active' || $server->status === 'online';
    }

    public function getServerLoad(Server $server): float
    {
        return $this->getCurrentLoad($server->id)['load_percentage'] ?? 0;
    }

    public function getServerStats(Server $server): array
    {
        return [
            'id' => $server->id,
            'name' => $server->name,
            'status' => $server->status,
            'load' => $this->getServerLoad($server),
            'active_streams' => $server->streamAssignments()->count(),
            'current_connections' => $server->current_connections,
            'max_connections' => $server->max_connections,
            'cpu_usage' => $this->getServerMetric($server, 'cpu'),
            'memory_usage' => $this->getServerMetric($server, 'memory'),
        ];
    }

    private function getServerMetric(Server $server, string $metric): float
    {
        $cacheKey = "server:metric:{$server->id}:{$metric}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($server, $metric) {
            return rand(20, 70);
        });
    }
}