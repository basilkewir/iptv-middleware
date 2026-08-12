<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Contracts\Streaming\LoadBalancerInterface;
use App\Models\Server;
use App\Models\Channel;
use App\Enums\Server\ServerStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoadBalancer implements LoadBalancerInterface
{
    private const CACHE_TTL = 60;
    private const MAX_LOAD_THRESHOLD = 85;

    public function selectServer(Channel $channel): ?Server
    {
        $servers = $this->getAvailableServers();

        if ($servers->isEmpty()) {
            Log::warning('No available servers for channel', ['channel_id' => $channel->id]);
            return null;
        }

        $selectedServer = $this->applyStrategy($servers, $channel);

        if (!$selectedServer) {
            Log::warning('Load balancer could not select server', ['channel_id' => $channel->id]);
            return null;
        }

        Log::info('Server selected for channel', [
            'channel_id' => $channel->id,
            'server_id' => $selectedServer->id,
            'strategy' => config('streaming.load_balancer.strategy', 'least_connections'),
        ]);

        return $selectedServer;
    }

    public function getAvailableServers(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(
            'loadbalancer:available_servers',
            self::CACHE_TTL,
            fn () => Server::where('status', ServerStatus::ACTIVE)
                ->where('is_enabled', true)
                ->where('current_load', '<', self::MAX_LOAD_THRESHOLD)
                ->get()
        );
    }

    public function getServerLoad(Server $server): float
    {
        $cachedLoad = Cache::get("server:load:{$server->id}");

        if ($cachedLoad !== null) {
            return (float) $cachedLoad;
        }

        return (float) $server->current_load;
    }

    public function updateServerLoad(Server $server): void
    {
        $connections = Cache::get("stream:server:{$server->id}:count", 0);
        $maxConnections = $server->max_connections ?: 1000;

        $load = ($connections / $maxConnections) * 100;

        $server->update(['current_load' => $load]);
        Cache::put("server:load:{$server->id}", $load, self::CACHE_TTL);
    }

    public function getServerStats(Server $server): array
    {
        $load = $this->getServerLoad($server);
        $activeStreams = Cache::get("stream:server:{$server->id}:count", 0);

        return [
            'server_id' => $server->id,
            'load' => $load,
            'active_streams' => $activeStreams,
            'max_connections' => $server->max_connections,
            'status' => $server->status->value,
            'is_healthy' => $load < self::MAX_LOAD_THRESHOLD,
        ];
    }

    public function rebalance(): array
    {
        $servers = $this->getAvailableServers();

        if ($servers->count() <= 1) {
            return ['message' => 'Not enough servers to rebalance'];
        }

        $rebalanced = [];

        foreach ($servers as $server) {
            $this->updateServerLoad($server);
            $rebalanced[] = [
                'server_id' => $server->id,
                'new_load' => $server->current_load,
            ];
        }

        return $rebalanced;
    }

    public function isServerHealthy(Server $server): bool
    {
        if ($server->status !== ServerStatus::ACTIVE) {
            return false;
        }

        if (!$server->is_enabled) {
            return false;
        }

        $load = $this->getServerLoad($server);

        return $load < self::MAX_LOAD_THRESHOLD;
    }

    public function getBestServerForChannel(Channel $channel): ?Server
    {
        $servers = $this->getAvailableServers();

        if ($servers->isEmpty()) {
            return null;
        }

        return $servers->sortBy(function (Server $server) use ($channel) {
            $load = $this->getServerLoad($server);
            $priority = $this->calculateServerPriority($server, $channel);

            return $load - $priority;
        })->first();
    }

    private function applyStrategy(\Illuminate\Database\Eloquent\Collection $servers, Channel $channel): ?Server
    {
        $strategy = config('streaming.load_balancer.strategy', 'least_connections');

        return match ($strategy) {
            'round_robin' => $this->roundRobin($servers),
            'least_connections' => $this->leastConnections($servers),
            'weighted' => $this->weightedSelection($servers),
            'ip_hash' => $this->ipHash($servers, $channel),
            default => $this->leastConnections($servers),
        };
    }

    private function roundRobin(\Illuminate\Database\Eloquent\Collection $servers): Server
    {
        $lastServerId = Cache::get('loadbalancer:round_robin:index', 0);
        $serverList = $servers->values();
        $index = ($lastServerId + 1) % $serverList->count();

        Cache::put('loadbalancer:round_robin:index', $index, 3600);

        return $serverList[$index];
    }

    private function leastConnections(\Illuminate\Database\Eloquent\Collection $servers): Server
    {
        return $servers->sortBy(function (Server $server) {
            return $this->getServerLoad($server);
        })->first();
    }

    private function weightedSelection(\Illuminate\Database\Eloquent\Collection $servers): Server
    {
        $weightedServers = $servers->map(function (Server $server) {
            $load = $this->getServerLoad($server);
            $weight = max(1, 100 - $load);

            return [
                'server' => $server,
                'weight' => $weight,
            ];
        });

        $totalWeight = $weightedServers->sum('weight');
        $random = rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($weightedServers as $weighted) {
            $cumulative += $weighted['weight'];

            if ($random <= $cumulative) {
                return $weighted['server'];
            }
        }

        return $servers->first();
    }

    private function ipHash(\Illuminate\Database\Eloquent\Collection $servers, Channel $channel): Server
    {
        $ip = request()->ip() ?? '127.0.0.1';
        $hash = crc32($ip);
        $index = $hash % $servers->count();

        return $servers->values()[$index];
    }

    private function calculateServerPriority(Server $server, Channel $channel): float
    {
        $priority = 0;

        if ($server->region === $channel->region) {
            $priority += 10;
        }

        if (in_array($server->id, $channel->preferred_servers ?? [])) {
            $priority += 20;
        }

        $load = $this->getServerLoad($server);
        $priority -= $load * 0.1;

        return $priority;
    }

    private function clearServerCache(): void
    {
        Cache::forget('loadbalancer:available_servers');
    }
}
