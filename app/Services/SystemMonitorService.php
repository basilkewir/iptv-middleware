<?php

namespace App\Services;

use App\Models\Channel;

class SystemMonitorService
{
    /**
     * Collect real host metrics for the dashboard.
     */
    public function summary(): array
    {
        return [
            'hostname' => gethostname() ?: 'unknown',
            'os' => $this->osName(),
            'cpu_usage' => $this->cpuUsage(),
            'memory_usage' => $this->memoryUsage(),
            'memory_used_mb' => $this->memoryUsedMb(),
            'memory_total_mb' => $this->memoryTotalMb(),
            'disk_usage' => $this->diskUsage(),
            'disk_used_gb' => $this->diskUsedGb(),
            'disk_total_gb' => $this->diskTotalGb(),
            'load' => $this->loadAverages(),
            'uptime' => $this->uptimeHuman(),
            'php_version' => PHP_VERSION,
            'ingests' => $this->ingestStatuses(),
            'nics' => $this->nicStats(),
            'collected_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Per-NIC traffic stats (totals + throughput since the previous sample).
     * Rates are derived from cached samples so no artificial sleeps are needed;
     * the first request after boot shows zero rates until a second sample lands.
     *
     * @return array<int, array{name: string, up: bool, ip: string|null, rx_mbps: float, tx_mbps: float, rx_total_gb: float, tx_total_gb: float}>
     */
    public function nicStats(): array
    {
        $now = microtime(true);
        $sample = [];

        foreach (glob('/sys/class/net/*') ?: [] as $path) {
            $iface = basename($path);

            if ($iface === 'lo') {
                continue;
            }

            $rx = (int) @file_get_contents($path . '/statistics/rx_bytes');
            $tx = (int) @file_get_contents($path . '/statistics/tx_bytes');
            $operstate = trim((string) @file_get_contents($path . '/operstate'));

            $sample[$iface] = [
                'rx' => $rx,
                'tx' => $tx,
                'up' => $operstate === 'up',
                'ip' => $this->interfaceIp($iface),
            ];
        }

        $previous = cache()->get('monitor:net_sample');
        cache()->put('monitor:net_sample', ['t' => $now, 'data' => array_map(fn ($s) => ['rx' => $s['rx'], 'tx' => $s['tx']], $sample)], 300);

        $result = [];

        foreach ($sample as $name => $nic) {
            $rxMbps = 0.0;
            $txMbps = 0.0;

            if (isset($previous['data'][$name])) {
                $dt = $now - (float) $previous['t'];

                if ($dt >= 0.5) {
                    $rxMbps = round(max(0, $nic['rx'] - $previous['data'][$name]['rx']) * 8 / $dt / 1_000_000, 2);
                    $txMbps = round(max(0, $nic['tx'] - $previous['data'][$name]['tx']) * 8 / $dt / 1_000_000, 2);
                }
            }

            $result[] = [
                'name' => $name,
                'up' => $nic['up'],
                'ip' => $nic['ip'],
                'rx_mbps' => $rxMbps,
                'tx_mbps' => $txMbps,
                'rx_total_gb' => round($nic['rx'] / 1024 ** 3, 2),
                'tx_total_gb' => round($nic['tx'] / 1024 ** 3, 2),
            ];
        }

        return $result;
    }

    private function interfaceIp(string $iface): ?string
    {
        $ips = @shell_exec("ip -4 -o addr show dev " . escapeshellarg($iface) . " 2>/dev/null | awk '{print \$4}' | head -1");

        return $ips ? trim(explode('/', trim($ips))[0]) : null;
    }

    public function osName(): string
    {
        $release = '/etc/os-release';

        if (is_readable($release)) {
            foreach (file($release, FILE_IGNORE_NEW_LINES) as $line) {
                if (str_starts_with($line, 'PRETTY_NAME=')) {
                    return trim(explode('=', $line, 2)[1], '"');
                }
            }
        }

        return PHP_OS;
    }

    /**
     * Sample /proc/stat twice and derive busy percentage.
     * Falls back to 1-minute load vs core count when /proc/stat is unavailable.
     */
    public function cpuUsage(): float
    {
        if (is_readable('/proc/stat')) {
            $a = $this->procStatTotals();
            usleep(200_000);
            $b = $this->procStatTotals();

            $dIdle = ($b['idle'] ?? 0) - ($a['idle'] ?? 0);
            $dTotal = array_sum($b) - array_sum($a);

            if ($dTotal > 0) {
                return round(max(0.0, min(100.0, (1 - ($dIdle / $dTotal)) * 100)), 1);
            }
        }

        $cores = $this->coreCount() ?: 1;
        $load = sys_getloadavg()[0] ?? 0;

        return round(min(100.0, ($load / $cores) * 100), 1);
    }

    private function procStatTotals(): array
    {
        $line = (string) file_get_contents('/proc/stat');
        $parts = preg_split('/\s+/', trim((string) strstr($line, "\n", true) ?: $line));

        // cpu user nice system idle iowait irq softirq steal ...
        [$label, $user, $nice, $system, $idle, $iowait, $irq, $softirq, $steal] = array_pad($parts, 9, 0);

        return [
            'user' => (int) $user,
            'nice' => (int) $nice,
            'system' => (int) $system,
            'idle' => (int) $idle + (int) $iowait,
            'irq' => (int) $irq,
            'softirq' => (int) $softirq,
            'steal' => (int) $steal,
        ];
    }

    public function memoryUsage(): float
    {
        [$total, $available] = $this->memoryInfo();

        if ($total > 0) {
            return round((($total - $available) / $total) * 100, 1);
        }

        return 0.0;
    }

    public function memoryUsedMb(): int
    {
        [$total, $available] = $this->memoryInfo();

        return (int) max(0, round(($total - $available) / 1024));
    }

    public function memoryTotalMb(): int
    {
        [$total] = $this->memoryInfo();

        return (int) round($total / 1024);
    }

    /** @return array{0: float, 1: float} total/available in kB */
    private function memoryInfo(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return [0.0, 0.0];
        }

        $values = [];

        foreach (file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$key, $val] = array_pad(explode(':', $line, 2), 2, '');
            $key = trim($key);
            $values[$key] = (float) filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }

        $total = $values['MemTotal'] ?? 0.0;
        $available = $values['MemAvailable'] ?? ($values['MemFree'] ?? 0.0);

        return [$total, $available];
    }

    public function diskUsage(): float
    {
        $base = storage_path();

        $total = @disk_total_space($base);
        $free = @disk_free_space($base);

        if (! $total || $free === false) {
            $total = @disk_total_space('/');
            $free = @disk_free_space('/') ?: 0;
        }

        if (! $total) {
            return 0.0;
        }

        return round((($total - $free) / $total) * 100, 1);
    }

    public function diskUsedGb(): float
    {
        $base = storage_path();
        $total = @disk_total_space($base) ?: @disk_total_space('/');
        $free = @disk_free_space($base) ?: @disk_free_space('/') ?: 0;

        return round(($total - $free) / (1024 ** 3), 1);
    }

    public function diskTotalGb(): float
    {
        $total = @disk_total_space(storage_path()) ?: @disk_total_space('/');

        return round(($total ?: 0) / (1024 ** 3), 1);
    }

    public function loadAverages(): array
    {
        $load = sys_getloadavg() ?: [0, 0, 0];

        return [
            array_map(fn ($v) => round((float) $v, 2), array_pad($load, 3, 0)),
            'cores' => $this->coreCount(),
        ][0];
    }

    public function coreCount(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            return substr_count((string) file_get_contents('/proc/cpuinfo'), 'processor') ?: 1;
        }

        return (int) (shell_exec('nproc') ?: 1);
    }

    public function uptimeHuman(): string
    {
        if (! is_readable('/proc/uptime')) {
            return 'unknown';
        }

        $secs = (int) floatval((string) file_get_contents('/proc/uptime'));
        $days = intdiv($secs, 86400);
        $hours = intdiv($secs % 86400, 3600);
        $mins = intdiv($secs % 3600, 60);

        return match (true) {
            $days > 0 => "{$days}d {$hours}h",
            $hours > 0 => "{$hours}h {$mins}m",
            default => "{$mins}m",
        };
    }

    /**
     * Per-channel HLS ingest health based on segment freshness on disk.
     *
     * @return array<int, array{id: int, name: string, channel_number: mixed, status: string, last_segment_age: int|null}>
     */
    public function ingestStatuses(int $freshSeconds = 30): array
    {
        $root = storage_path('app/streams/hls');

        return Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->orderBy('channel_number')
            ->get()
            ->map(function (Channel $channel) use ($root, $freshSeconds) {
                $dir = $root . DIRECTORY_SEPARATOR . $channel->id;
                $playlist = $dir . DIRECTORY_SEPARATOR . 'playlist.m3u8';

                $status = 'down';
                $age = null;

                if (is_file($playlist)) {
                    $age = time() - (int) filemtime($playlist);
                    $status = $age <= $freshSeconds ? 'live' : 'stale';
                } elseif (is_dir($dir)) {
                    $status = 'starting';
                }

                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'channel_number' => $channel->channel_number,
                    'status' => $status,
                    'last_segment_age' => $age,
                ];
            })
            ->toArray();
    }
}
