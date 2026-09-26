<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelBroadcast;
use App\Models\Channel;
use App\Services\AdminChannel\MyChannelHlsService;
use App\Services\StreamingService\MulticastIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Ensure all active channel ingests are running.
 *
 * This is the core command for the standalone ingest architecture.
 * It starts one persistent FFmpeg process per active channel (or per
 * multicast group for UDP sources) and keeps them running.
 *
 * Scheduled every minute by the kernel. Safe to call repeatedly —
 * already-running ingests are skipped.
 */
class EnsureAllIngest extends Command
{
    protected $signature = 'ingest:ensure-all';

    protected $description = 'Ensure all channel ingests are running (persistent background ingestion)';

    public function handle(
        MulticastIngestService $multicast,
        MyChannelHlsService $hls,
    ): int {
        $errors = 0;

        // ── 1. Multicast group readers (UDP/RTP) ───────────────────────────
        $this->line('── Multicast readers ──');
        $udpChannels = Channel::where('stream_type', 'udp')
            ->where('is_active', true)
            ->orderBy('channel_number')
            ->get();

        if ($udpChannels->isEmpty()) {
            $this->info('  No active UDP channels.');
        } else {
            $sourceUrls = $udpChannels->groupBy('stream_url');
            foreach ($sourceUrls as $url => $group) {
                $ch = $group->first();
                $ok = $multicast->ensureGroupReader($ch);
                $status = $ok ? 'OK' : 'FAIL';
                $this->line("  {$status} source={$url} channels={$group->count()}");
                if (! $ok) {
                    $errors++;
                }
            }
        }

        // ── 2. Regular channel ingests (HTTP/HLS/RTMP/YouTube) ─────────────
        $this->line('── Regular channel ingests ──');
        $regularChannels = Channel::where('is_active', true)
            ->where('stream_type', '!=', 'udp')
            ->orderBy('channel_number')
            ->get();

        foreach ($regularChannels as $channel) {
            $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;
            if (empty($sourceUrl)) {
                $this->line("  SKIP {$channel->name} (no source URL)");
                continue;
            }

            $outputDir = storage_path("app/streams/hls/{$channel->id}");
            $pidFile = $outputDir . '/ingest.pid';

            if ($this->isIngestAlive($pidFile, $channel->id)) {
                $this->line("  OK {$channel->name}");
                continue;
            }

            // Start ingest via the XtreamController helper
            $xtream = app(\App\Http\Controllers\XtreamController::class);
            $xtream->ensureHlsStream(
                (int) $channel->id,
                $sourceUrl,
                $channel->program_number,
                $channel->local_address,
                (bool) ($channel->transcoding_enabled ?? false)
            );
            $this->line("  STARTED {$channel->name}");
        }

        // ── 3. Admin channel playout ────────────────────────────────────────
        $this->line('── Admin channel playout ──');
        $adminChannels = AdminChannel::where('is_active', true)->get();

        foreach ($adminChannels as $channel) {
            if ($hls->isRunning($channel)) {
                if ($hls->isStalled($channel) && $channel->broadcast_status === 'live') {
                    $this->line("  RELAUNCH {$channel->channel_name} (stalled)");
                    $hls->restartKeepingSegments($channel);
                } else {
                    $this->line("  OK {$channel->channel_name}");
                }
                continue;
            }

            if ($channel->broadcast_status !== 'live') {
                $this->line("  SKIP {$channel->channel_name} (stopped)");
                continue;
            }

            $started = $this->startPlayout($channel, $hls);
            $this->line(($started ? '  OK' : '  FAIL') . " {$channel->channel_name}");
            if (! $started) {
                $errors++;
            }
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Check if a regular channel's FFmpeg ingest is alive and healthy.
     */
    private function isIngestAlive(string $pidFile, int $channelId): bool
    {
        if (! is_file($pidFile)) {
            return false;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));
        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            @unlink($pidFile);
            return false;
        }

        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
        if ($cmdline === false || ! str_contains($cmdline, 'ffmpeg')) {
            return false;
        }

        // Check for stale playlist (frozen ingest)
        $outputDir = storage_path("app/streams/hls/{$channelId}");
        $playlist = $outputDir . '/playlist.m3u8';
        if (is_file($playlist)) {
            $age = time() - (int) @filemtime($playlist);
            if ($age > 90) {
                return false; // Frozen — needs restart
            }
        }

        return true;
    }

    private function startPlayout(AdminChannel $channel, MyChannelHlsService $hls): bool
    {
        $broadcast = MyChannelBroadcast::create([
            'channel_id' => $channel->id,
            'session_id' => Str::uuid()->toString(),
            'start_time' => now(),
            'scheduled_end' => now()->addHours(24),
            'status' => 'starting',
            'playlist_snapshot' => $channel->myChannelPlaylist()->with('content')->get()->toJson(),
        ]);

        return $hls->start($broadcast);
    }
}
