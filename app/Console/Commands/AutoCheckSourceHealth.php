<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\XtreamController;
use App\Models\Channel;
use App\Services\StreamingService\SourceHealthCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Automatically check source health for all active channels every 60s.
 *
 * Probes each active channel's source URL via ffprobe and updates
 * source_status in the DB. When a source goes offline, automatically
 * failovers to backup URLs if available.
 *
 * IMPORTANT: Audio detection is informational only — it is NOT used
 * to trigger restarts because audio probes can give false negatives
 * for HTTPS HLS sources, and restarting the ingest mid-playback
 * causes visible freezes for the user.
 *
 * Failover priority: Primary → Backup 1 → Backup 2
 */
class AutoCheckSourceHealth extends Command
{
    protected $signature = 'channels:auto-check-health
                            {--dry-run : Report status without restarting }';

    protected $description = 'Probe all active channel sources, detect failures, auto-failover to backups';

    /**
     * Only auto-restart UDP/multicast ingests. HTTP/HLS sources that go
     * offline should be left alone — restarting the ingest won't fix an
     * upstream outage and just causes more disruption.
     */
    private function shouldAutoRestart(Channel $channel, string $sourceUrl): bool
    {
        return str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://');
    }

    public function handle(SourceHealthCheckService $healthCheck): int
    {
        $channels = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels to check.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $online = 0;
        $offline = 0;
        $noAudio = 0;
        $restarted = 0;
        $failedOver = 0;

        foreach ($channels as $channel) {
            $statusLabel = "ch#{$channel->channel_number} {$channel->name}";
            $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

            // Skip channels the admin has manually pinned to a backup source.
            // Auto-health-check must not fight manual operator decisions.
            if (Cache::has("channel:manual_override:{$channel->id}")) {
                $this->line("  {$statusLabel} — skipped (manual override active)");
                continue;
            }

            // Probe the currently active source
            $result = $healthCheck->checkSource($channel);
            $fresh = $channel->fresh();

            if ($fresh->source_status === 'online') {
                // Channel is reachable — check for audio presence (informational only)
                $hasAudio = $healthCheck->probeForAudio($sourceUrl, $fresh);

                if ($hasAudio) {
                    $this->info("  {$statusLabel} — OK (audio+video) [{$fresh->active_source_label}]");
                } else {
                    $noAudio++;
                    $this->warn("  {$statusLabel} — NO AUDIO [{$fresh->active_source_label}] (informational — no action taken)");
                }

                $online++;
                continue;
            }

            // Source is offline
            $offline++;
            $this->error("  {$statusLabel} — OFFLINE: {$result['message']} [{$fresh->active_source_label}]");

            if ($dryRun) {
                continue;
            }

            // Try failover to backup URLs immediately on first offline detection.
            // The watchdog (channels:watchdog) already enforces a 10s grace period
            // before switching, so no additional attempt counter is needed here.
            $isAutoRestartable = $this->shouldAutoRestart($fresh, $sourceUrl);

            if ($isAutoRestartable) {
                $this->line("    Force-restarting UDP group reader...");
                app(XtreamController::class)->restartHlsStream($fresh);
                $restarted++;
                continue;
            }

            // Try failover to backup URLs (works for all source types)
            $failoverResult = $healthCheck->tryBackupUrls($fresh);
            if ($failoverResult['success']) {
                $this->info("    Failover: {$failoverResult['message']}");
                $failedOver++;
            } else {
                $this->error("    All sources offline: {$failoverResult['message']}");
            }
        }

        $this->info(sprintf(
            'Done — checked: %d, online: %d, offline: %d, no-audio: %d (info only), restarted: %d, failed-over: %d',
            $channels->count(),
            $online,
            $offline,
            $noAudio,
            $restarted,
            $failedOver
        ));

        Log::info('Auto source health check completed', [
            'total' => $channels->count(),
            'online' => $online,
            'offline' => $offline,
            'no_audio' => $noAudio,
            'restarted' => $restarted,
            'failed_over' => $failedOver,
        ]);

        return self::SUCCESS;
    }
}
