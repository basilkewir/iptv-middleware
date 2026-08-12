<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Models\Stream;
use App\Models\Server;
use App\Enums\Stream\StreamStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StreamMonitor
{
    private StreamManager $streamManager;
    private LoadBalancer $loadBalancer;

    private const MONITOR_CACHE_TTL = 60;
    private const ALERT_THRESHOLD_CPU = 90;
    private const ALERT_THRESHOLD_MEMORY = 85;
    private const ALERT_THRESHOLD_CONNECTIONS = 0.9;

    public function __construct(StreamManager $streamManager, LoadBalancer $loadBalancer)
    {
        $this->streamManager = $streamManager;
        $this->loadBalancer = $loadBalancer;
    }

    public function getStreamHealth(Stream $stream): array
    {
        $cacheKey = "stream:health:{$stream->id}";

        return Cache::remember($cacheKey, self::MONITOR_CACHE_TTL, function () use ($stream) {
            $connections = $this->streamManager->getStreamConnections($stream);
            $uptime = $stream->started_at
                ? now()->diffInSeconds($stream->started_at)
                : 0;

            $score = $this->calculateHealthScore($stream, $connections, $uptime);

            return [
                'stream_id' => $stream->id,
                'status' => $stream->status->value,
                'connections' => $connections,
                'uptime' => $uptime,
                'health_score' => $score,
                'is_healthy' => $score >= 70,
                'last_checked' => now()->toISOString(),
            ];
        });
    }

    public function getServerHealth(Server $server): array
    {
        $cacheKey = "server:health:{$server->id}";

        return Cache::remember($cacheKey, self::MONITOR_CACHE_TTL, function () use ($server) {
            $stats = $this->loadBalancer->getServerStats($server);
            $streamCount = $this->streamManager->getServerStreamCount($server->id);

            $alerts = $this->checkServerAlerts($server, $stats);

            return [
                'server_id' => $server->id,
                'name' => $server->name,
                'status' => $server->status->value,
                'load' => $stats['load'],
                'active_streams' => $streamCount,
                'max_connections' => $server->max_connections,
                'utilization' => $this->calculateUtilization($streamCount, $server->max_connections),
                'alerts' => $alerts,
                'is_healthy' => empty($alerts),
                'last_checked' => now()->toISOString(),
            ];
        });
    }

    public function getSystemOverview(): array
    {
        return Cache::remember('system:overview', self::MONITOR_CACHE_TTL, function () {
            $totalStreams = Stream::where('status', StreamStatus::ACTIVE)->count();
            $totalConnections = Stream::where('status', StreamStatus::ACTIVE)
                ->sum('current_viewers');
            $activeServers = Server::where('status', 'active')->count();
            $totalServers = Server::count();

            return [
                'total_streams' => $totalStreams,
                'total_connections' => $totalConnections,
                'active_servers' => $activeServers,
                'total_servers' => $totalServers,
                'server_utilization' => $totalServers > 0
                    ? round(($activeServers / $totalServers) * 100, 2)
                    : 0,
                'avg_connections_per_stream' => $totalStreams > 0
                    ? round($totalConnections / $totalStreams, 2)
                    : 0,
                'last_updated' => now()->toISOString(),
            ];
        });
    }

    public function getStreamMetrics(Stream $stream, int $minutes = 60): array
    {
        $cacheKey = "stream:metrics:{$stream->id}:{$minutes}";

        return Cache::remember($cacheKey, 30, function () use ($stream, $minutes) {
            $start = now()->subMinutes($minutes);

            return [
                'stream_id' => $stream->id,
                'period_minutes' => $minutes,
                'peak_connections' => $this->getPeakConnections($stream, $start),
                'avg_connections' => $this->getAvgConnections($stream, $start),
                'total_unique_viewers' => $this->getUniqueViewers($stream, $start),
                'buffer_underruns' => $this->getBufferUnderruns($stream, $start),
                'error_count' => $this->getErrorCount($stream, $start),
            ];
        });
    }

    public function getAlerts(): array
    {
        $alerts = [];

        $servers = Server::where('status', 'active')->get();

        foreach ($servers as $server) {
            $health = $this->getServerHealth($server);

            if (!empty($health['alerts'])) {
                $alerts = array_merge($alerts, $health['alerts']);
            }
        }

        $streams = Stream::where('status', StreamStatus::ACTIVE)->get();

        foreach ($streams as $stream) {
            $health = $this->getStreamHealth($stream);

            if (!$health['is_healthy']) {
                $alerts[] = [
                    'type' => 'stream',
                    'stream_id' => $stream->id,
                    'message' => "Stream {$stream->id} health score is {$health['health_score']}",
                    'severity' => $health['health_score'] < 50 ? 'critical' : 'warning',
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $alerts;
    }

    public function getPerformanceReport(): array
    {
        return Cache::remember('performance:report', 300, function () {
            $totalStreams = Stream::where('status', StreamStatus::ACTIVE)->count();
            $totalConnections = Stream::where('status', StreamStatus::ACTIVE)
                ->sum('current_viewers');
            $avgBitrate = Stream::where('status', StreamStatus::ACTIVE)
                ->avg('bitrate');

            return [
                'total_active_streams' => $totalStreams,
                'total_connections' => $totalConnections,
                'avg_bitrate' => round($avgBitrate ?? 0, 2),
                'peak_hour' => $this->getPeakHour(),
                'most_popular_stream' => $this->getMostPopularStream(),
                'server_distribution' => $this->getServerDistribution(),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    private function calculateHealthScore(Stream $stream, int $connections, int $uptime): float
    {
        $score = 100;

        if ($stream->status !== StreamStatus::ACTIVE) {
            return 0;
        }

        $server = $stream->server;
        if ($server) {
            $load = $this->loadBalancer->getServerLoad($server);

            if ($load > 80) {
                $score -= 20;
            } elseif ($load > 60) {
                $score -= 10;
            }
        }

        if ($uptime < 60) {
            $score -= 10;
        }

        if ($connections === 0 && $uptime > 300) {
            $score -= 5;
        }

        return max(0, min(100, $score));
    }

    private function checkServerAlerts(Server $server, array $stats): array
    {
        $alerts = [];

        if ($stats['load'] > self::ALERT_THRESHOLD_CPU) {
            $alerts[] = [
                'type' => 'high_cpu',
                'server_id' => $server->id,
                'message' => "Server {$server->name} CPU load is {$stats['load']}%",
                'severity' => 'critical',
                'timestamp' => now()->toISOString(),
            ];
        }

        $utilization = $this->calculateUtilization($stats['active_streams'], $server->max_connections);

        if ($utilization > self::ALERT_THRESHOLD_CONNECTIONS * 100) {
            $alerts[] = [
                'type' => 'high_connections',
                'server_id' => $server->id,
                'message' => "Server {$server->name} connection utilization is {$utilization}%",
                'severity' => 'warning',
                'timestamp' => now()->toISOString(),
            ];
        }

        if (!$this->loadBalancer->isServerHealthy($server)) {
            $alerts[] = [
                'type' => 'unhealthy',
                'server_id' => $server->id,
                'message' => "Server {$server->name} is marked as unhealthy",
                'severity' => 'critical',
                'timestamp' => now()->toISOString(),
            ];
        }

        return $alerts;
    }

    private function calculateUtilization(int $current, int $max): float
    {
        if ($max <= 0) {
            return 0;
        }

        return round(($current / $max) * 100, 2);
    }

    private function getPeakConnections(Stream $stream, $start): int
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $start)
            ->max('connections') ?? 0;
    }

    private function getAvgConnections(Stream $stream, $start): float
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $start)
            ->avg('connections') ?? 0;
    }

    private function getUniqueViewers(Stream $stream, $start): int
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $start)
            ->distinct('user_id')
            ->count('user_id');
    }

    private function getBufferUnderruns(Stream $stream, $start): int
    {
        return $stream->error_logs()
            ->where('type', 'buffer_underrun')
            ->where('created_at', '>=', $start)
            ->count();
    }

    private function getErrorCount(Stream $stream, $start): int
    {
        return $stream->error_logs()
            ->where('created_at', '>=', $start)
            ->count();
    }

    private function getPeakHour(): ?string
    {
        return Stream::selectRaw('HOUR(started_at) as hour, COUNT(*) as count')
            ->where('started_at', '>=', now()->subDays(7))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first()
            ?->hour;
    }

    private function getMostPopularStream(): ?array
    {
        return Stream::where('status', StreamStatus::ACTIVE)
            ->orderByDesc('current_viewers')
            ->first()
            ?->only(['id', 'channel_id', 'current_viewers']);
    }

    private function getServerDistribution(): array
    {
        return Stream::where('status', StreamStatus::ACTIVE)
            ->selectRaw('server_id, COUNT(*) as stream_count, SUM(current_viewers) as total_viewers')
            ->groupBy('server_id')
            ->get()
            ->toArray();
    }
}
