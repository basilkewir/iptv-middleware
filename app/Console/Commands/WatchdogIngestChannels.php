<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\XtreamController;
use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelBroadcast;
use App\Models\Channel;
use App\Services\AdminChannel\MyChannelHlsService;
use App\Services\StreamingService\SourceHealthCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WatchdogIngestChannels extends Command
{
    protected $signature = 'channels:watchdog';

    protected $description = 'Restart any HLS ingest or admin channel playout that has stopped, failover to backup after 10s';

    // Seconds a stream must be continuously stale before failover is attempted.
    private const FAILOVER_GRACE_SECONDS = 10;

    public function handle(XtreamController $xtream, MyChannelHlsService $hls, SourceHealthCheckService $healthCheck): int
    {
        // ── Multicast / HTTP channel ingests ──
        $channels = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->get();

        foreach ($channels as $channel) {
            // Never let one channel's failure abort the whole watchdog pass —
            // a single broken channel must not leave every other channel
            // without supervision (previously a log-write or probe exception
            // here killed the run and froze all ingest restarts).
            try {
                $this->watchChannel($xtream, $healthCheck, $channel);
            } catch (\Throwable $e) {
                Log::error('Watchdog channel check failed', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
                $this->error("  ch{$channel->id} {$channel->name}: {$e->getMessage()}");
            }
        }

        // ── Admin channel playouts ──
        $adminChannels = AdminChannel::where('is_active', true)->get();

        foreach ($adminChannels as $channel) {
            try {
                $this->watchAdminChannel($hls, $channel);
            } catch (\Throwable $e) {
                Log::error('Watchdog admin channel check failed', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
                $this->error("  admin {$channel->channel_name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function watchChannel(
        XtreamController $xtream,
        SourceHealthCheckService $healthCheck,
        Channel $channel
    ): void {
        $outputDir = storage_path("app/streams/hls/{$channel->id}");
        $staleKey  = "watchdog:stale_since:{$channel->id}";

        // ── On a backup: only auto-restore if the failover was automatic ──
        // Manual switches (set via admin UI) are respected — watchdog will
        // not override them. Auto-failovers are restored when primary recovers.
        if ((int) $channel->active_source_index > 0) {
            if (Cache::has("channel:manual_override:{$channel->id}")) {
                return; // Admin manually chose this source — leave it alone
            }
            $better = $healthCheck->switchToBestAvailableSource($channel, skip: $channel->active_source_index);
            if ($better['success']) {
                $this->info("  ch{$channel->id} {$channel->name}: restored to {$better['label']}");
                Log::info('Watchdog source upgrade', [
                    'channel_id'  => $channel->id,
                    'channel_name'=> $channel->name,
                    'to_label'    => $better['label'],
                    'to_url'      => $better['url'],
                ]);
                Cache::forget($staleKey);
            }
            return;
        }

        // ── On primary: check if ingest is healthy ──
        if (! is_dir($outputDir) || ! $this->isStale($outputDir)) {
            Cache::forget($staleKey);
            return;
        }

        // Ingest is stale — enforce grace period before acting
        $staleSince = Cache::get($staleKey);
        if ($staleSince === null) {
            Cache::put($staleKey, time(), 300);
            $this->line("  ch{$channel->id} {$channel->name}: stale, starting " . self::FAILOVER_GRACE_SECONDS . "s grace");
            return;
        }

        $downSeconds = time() - (int) $staleSince;
        if ($downSeconds < self::FAILOVER_GRACE_SECONDS) {
            return;
        }

        // Grace expired — verify the primary source is REALLY offline before
        // abandoning it. A stuck ffmpeg/group reader makes the playlist stale
        // but the source may still be healthy — in that case restart the
        // ingest IN PLACE on primary rather than failing over to a backup.
        // This enforces "primary is the main; only switch to backups when
        // the primary source is actually down."
        $this->line("  ch{$channel->id} {$channel->name}: down {$downSeconds}s, checking primary source");

        $primaryProbe = $healthCheck->probeSourceUrl($channel->stream_url, $channel, 0);

        if ($primaryProbe['status'] === 'online') {
            $this->line("    Primary source is STILL ONLINE — restarting ingest in place");
            Log::info('Watchdog primary source online, restarting ingest in place', [
                'channel_id'   => $channel->id,
                'channel_name' => $channel->name,
                'reason'       => 'playlist stale but primary source healthy',
            ]);
            $xtream->restartHlsStream($channel);
            Cache::forget($staleKey);

            // Refresh per-source status so the UI still shows primary as live.
            $healthCheck->probeAllSources($channel);
            return;
        }

        // Primary source is genuinely offline — fail over to the best backup.
        $this->line("  ch{$channel->id} {$channel->name}: primary offline, switching source");
        $result = $healthCheck->switchToBestAvailableSource($channel, skip: 0);

        if ($result['success']) {
            $this->info("    Switched to {$result['label']}: {$result['url']}");
            Log::info('Watchdog failover', [
                'channel_id'   => $channel->id,
                'channel_name' => $channel->name,
                'to_label'     => $result['label'],
                'to_url'       => $result['url'],
                'down_seconds' => $downSeconds,
            ]);
        } else {
            // All sources offline — force-restart to kill any zombie group reader
            $this->warn("    All sources offline, force-restarting");
            $xtream->restartHlsStream($channel);
        }

        Cache::forget($staleKey);
    }

    private function watchAdminChannel(MyChannelHlsService $hls, AdminChannel $channel): void
    {
        if (! $hls->isRunning($channel)) {
            // Respect explicit End Broadcast: only auto-restart channels the
            // admin has put live. A stopped channel stays stopped.
            if ($channel->broadcast_status !== 'live') {
                $this->line("  - skipping stopped admin channel: {$channel->channel_name}");
                return;
            }

            $this->line("  - restarting dead admin playout: {$channel->channel_name}");
        } elseif ($hls->isStalled($channel)) {
            // Process is alive but the stream stopped advancing (frozen
            // ffmpeg). Restart in place, preserving HLS continuity.
            if ($channel->broadcast_status !== 'live') {
                $this->line("  - skipping stopped admin channel: {$channel->channel_name}");
                return;
            }

            $this->line("  - restarting stalled admin playout: {$channel->channel_name}");
            $hls->restartKeepingSegments($channel);
            return;
        } else {
            return;
        }

        $broadcast = MyChannelBroadcast::create([
            'channel_id' => $channel->id,
            'session_id' => Str::uuid()->toString(),
            'start_time' => now(),
            'scheduled_end' => now()->addHours(24),
            'status' => 'starting',
            'playlist_snapshot' => $channel->myChannelPlaylist()->with('content')->get()->toJson(),
        ]);

        $hls->start($broadcast);
    }

    private function isStale(string $outputDir): bool
    {
        // Segments are deleted by HLS (-hls_flags delete_segments), so checking
        // segment mtime almost always returns newest=0 — a false negative that
        // masks frozen group readers. Use playlist.m3u8 instead: a healthy
        // ingest rewrites it every ~6s.
        $playlist = $outputDir . '/playlist.m3u8';

        if (! is_file($playlist)) {
            // No playlist yet — give a fresh ingest time to write its first one.
            if (! is_dir($outputDir)) {
                return false;
            }
            $dirAge = time() - (int) @filemtime($outputDir);
            return $dirAge > XtreamController::INGEST_STALE_SECONDS;
        }

        return (time() - (int) @filemtime($playlist)) > XtreamController::INGEST_STALE_SECONDS;
    }
}
