<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamingService\FlussonicService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Checks every active channel's stream source in Flussonic.
 * Restarts the input when the source is offline or frozen (bitrate = 0).
 *
 * "Frozen" means Flussonic is connected but receiving no data — the UDP
 * multicast source is sending silence or the satellite receiver has stalled.
 *
 * Runs every minute via the scheduler.
 */
class CheckStreamSources extends Command
{
    protected $signature = 'streams:check-sources
                            {--dry-run : Report issues without restarting}';

    protected $description = 'Check all stream sources in Flussonic and restart frozen or offline inputs';

    // Bitrate below this (bits/s) is treated as a frozen/dead source
    private const FROZEN_BITRATE_THRESHOLD = 10_000;

    // How many consecutive frozen checks before we restart (avoids flapping)
    private const FROZEN_STRIKES = 2;

    // Cache TTL for strike counters (seconds) — slightly longer than check interval
    private const STRIKE_TTL = 180;

    public function handle(FlussonicService $flussonic): int
    {
        $channels = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels.');
            return self::SUCCESS;
        }

        $dryRun    = (bool) $this->option('dry-run');
        $restarted = 0;
        $healthy   = 0;
        $offline   = 0;

        foreach ($channels as $channel) {
            $name = $this->streamName($channel);
            $stats = $flussonic->getStreamStats($name);

            if ($stats === null) {
                // Flussonic doesn't know this stream at all — skip, ingest-all will create it
                continue;
            }

            $status = $this->evaluate($stats);

            if ($status === 'healthy') {
                $this->clearStrikes($name);
                $healthy++;
                continue;
            }

            $offline++;
            $strikes = $this->incrementStrikes($name);

            $this->line(sprintf(
                '  [%s] ch#%d %s — %s (strike %d/%d)',
                $dryRun ? 'DRY' : 'ACT',
                $channel->channel_number,
                $channel->name,
                $status,
                $strikes,
                self::FROZEN_STRIKES
            ));

            if ($strikes < self::FROZEN_STRIKES) {
                continue;
            }

            $this->clearStrikes($name);

            if ($dryRun) {
                continue;
            }

            $ok = $flussonic->restartStreamInput($name);

            Log::warning('Stream source restarted', [
                'channel_id'     => $channel->id,
                'channel_name'   => $channel->name,
                'flussonic_name' => $name,
                'reason'         => $status,
                'success'        => $ok,
            ]);

            if ($ok) {
                $restarted++;
            }
        }

        $this->info(sprintf(
            'Done — healthy: %d, issues: %d, restarted: %d',
            $healthy,
            $offline,
            $restarted
        ));

        return self::SUCCESS;
    }

    /**
     * Evaluate Flussonic stream stats and return a status string.
     *
     * Flussonic API v3 stream object fields used:
     *   alive         (bool)   — input is connected
     *   input_bitrate (int)    — current ingest bitrate in bits/s
     */
    private function evaluate(array $stats): string
    {
        $alive   = (bool) ($stats['alive'] ?? false);
        $bitrate = (int)  ($stats['input_bitrate'] ?? $stats['bitrate'] ?? 0);

        if (! $alive) {
            return 'offline';
        }

        if ($bitrate < self::FROZEN_BITRATE_THRESHOLD) {
            return 'frozen';
        }

        return 'healthy';
    }

    private function streamName(Channel $channel): string
    {
        return $channel->slug ?: 'ch-' . $channel->id;
    }

    private function strikeKey(string $name): string
    {
        return "stream:source:strikes:{$name}";
    }

    private function incrementStrikes(string $name): int
    {
        $key = $this->strikeKey($name);
        $strikes = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $strikes, self::STRIKE_TTL);
        return $strikes;
    }

    private function clearStrikes(string $name): void
    {
        Cache::forget($this->strikeKey($name));
    }
}
