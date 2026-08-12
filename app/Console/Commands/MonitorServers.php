<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StreamingServer;
use App\Models\User;
use App\Services\StreamingService\LoadBalancer;
use App\Services\StreamingService\StreamManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorServers extends Command
{
    protected $signature = 'servers:monitor
                            {--check-health : Perform health check on all servers}
                            {--rebalance : Rebalance streams across servers}
                            {--notify : Send notifications for unhealthy servers}';

    protected $description = 'Monitor server health and performance';

    private LoadBalancer $loadBalancer;
    private StreamManager $streamManager;

    public function __construct(LoadBalancer $loadBalancer, StreamManager $streamManager)
    {
        parent::__construct();
        $this->loadBalancer = $loadBalancer;
        $this->streamManager = $streamManager;
    }

    public function handle(): int
    {
        $this->info('Server monitoring started...');

        $servers = StreamingServer::all();

        if ($servers->isEmpty()) {
            $this->warn('No servers configured.');
            return Command::SUCCESS;
        }

        $this->info("Monitoring {$servers->count()} server(s)...");

        $unhealthyServers = [];

        foreach ($servers as $server) {
            try {
                $status = $this->checkServerHealth($server);
                $this->displayServerStatus($server, $status);

                if (!$status['is_healthy']) {
                    $unhealthyServers[] = ['server' => $server, 'status' => $status];
                }
            } catch (\Exception $e) {
                $this->error("  Error checking server {$server->name}: {$e->getMessage()}");
                $unhealthyServers[] = ['server' => $server, 'error' => $e->getMessage()];
            }
        }

        if ($this->option('rebalance') && empty($unhealthyServers)) {
            $this->info('');
            $this->info('Rebalancing streams...');
            $results = $this->loadBalancer->rebalance();
            $this->info("Rebalanced " . count($results) . " server(s).");
        }

        if ($this->option('notify') && !empty($unhealthyServers)) {
            $this->sendNotifications($unhealthyServers);
        }

        $this->info('');
        $this->info("Monitoring completed. Unhealthy servers: " . count($unhealthyServers));

        return !empty($unhealthyServers) ? Command::FAILURE : Command::SUCCESS;
    }

    private function checkServerHealth(StreamingServer $server): array
    {
        $stats = $this->loadBalancer->getServerStats($server);
        $ping = $this->pingServer($server);
        $isHealthy = $server->is_active && $ping['success'];

        return [
            'is_healthy' => $isHealthy,
            'load' => $stats['load'] ?? 0,
            'active_streams' => $stats['active_streams'] ?? 0,
            'ping' => $ping,
            'status' => $server->is_active ? 'active' : 'inactive',
        ];
    }

    private function pingServer(StreamingServer $server): array
    {
        $startTime = microtime(true);

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'IPTV-Middleware/1.0'])
                ->get("http://{$server->host}:" . ($server->port ?? 80) . "/health");

            $latency = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => $response->successful(),
                'latency' => $latency,
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'latency' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function displayServerStatus(StreamingServer $server, array $status): void
    {
        $healthIcon = $status['is_healthy'] ? '✓' : '✗';
        $load = number_format($status['load'], 1);
        $latency = $status['ping']['latency'] ?? 0;

        $this->line("  [{$healthIcon}] {$server->name} ({$server->host})");
        $this->line("      Status: {$status['status']} | Load: {$load}% | Streams: {$status['active_streams']} | Latency: {$latency}ms");

        if (!$status['is_healthy']) {
            $this->error("      WARNING: Server is unhealthy!");

            if (isset($status['ping']['error'])) {
                $this->error("      Error: {$status['ping']['error']}");
            }
        }
    }

    private function sendNotifications(array $unhealthyServers): void
    {
        $this->info('');
        $this->info('Sending notifications for unhealthy servers...');

        $admins = User::where('is_admin', true)->get();

        foreach ($unhealthyServers as $item) {
            $server = $item['server'];
            $error = $item['error'] ?? 'Server is unhealthy';

            try {
                Log::critical('Server unhealthy', [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'server_host' => $server->host,
                    'error' => $error,
                ]);

                $this->info("  Notification sent for {$server->name}");
            } catch (\Exception $e) {
                $this->error("  Failed to send notification for {$server->name}: {$e->getMessage()}");
            }
        }
    }
}
