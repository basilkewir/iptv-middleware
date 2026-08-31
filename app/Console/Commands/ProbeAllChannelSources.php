<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamingService\SourceHealthCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Periodically probe every source (primary + backups) of a rotating batch of
 * active channels and persist per-source statuses for the admin UI.
 *
 * Runs on a schedule (default every 3 minutes) and processes a bounded subset
 * per run so we never fire more than a handful of concurrent ffprobes.
 */
class ProbeAllChannelSources extends Command
{
    protected $signature = 'channels:probe-sources';

    protected $description = 'Probe all sources (primary + backups) of active channels and persist per-source status';

    private const BATCH_SIZE = 15;

    public function handle(SourceHealthCheckService $healthCheck): int
    {
        $channels = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            // Least-recently-probed first so the whole catalogue gets covered
            // over successive runs even when many channels exist.
            ->orderBy('sources_last_probed_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels to probe.');
            return self::SUCCESS;
        }

        $probed = 0;
        foreach ($channels as $channel) {
            $lock = Cache::lock("source:status-probe:{$channel->id}", 40);

            if (! $lock->get()) {
                continue;
            }

            try {
                $statuses = $healthCheck->probeAllSources($channel);
                $probed++;
                $this->line("  ch{$channel->id} {$channel->name}: " . $this->describe($statuses));
            } finally {
                $lock->release();
            }
        }

        $this->info("Probed {$probed} channel(s).");
        Log::info('Source status probe completed', ['channels' => $probed]);

        return self::SUCCESS;
    }

    private function describe(array $statuses): string
    {
        $parts = [];
        foreach ([0, 1, 2] as $idx) {
            $s = $statuses[$idx] ?? null;
            if (! $s || $s['status'] === 'unconfigured') {
                continue;
            }
            $parts[] = $s['label'] . ':' . $s['status'];
        }
        return implode(', ', $parts) ?: 'no sources';
    }
}