<?php

declare(strict_types=1);

namespace App\Contracts\Streaming;

use App\Models\Server;
use App\Models\Channel;

interface LoadBalancerInterface
{
    public function selectServer(Channel $channel): ?Server;

    public function getAvailableServers(): \Illuminate\Database\Eloquent\Collection;

    public function getServerLoad(Server $server): float;

    public function updateServerLoad(Server $server): void;

    public function getServerStats(Server $server): array;

    public function rebalance(): array;

    public function isServerHealthy(Server $server): bool;

    public function getBestServerForChannel(Channel $channel): ?Server;
}
