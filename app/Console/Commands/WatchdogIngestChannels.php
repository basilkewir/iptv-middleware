<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\XtreamController;
use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelBroadcast;
use App\Models\Channel;
use App\Services\AdminChannel\MyChannelHlsService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WatchdogIngestChannels extends Command
{
    protected $signature = 'channels:watchdog';

    protected $description = 'Restart any HLS ingest or admin channel playout that has stopped';

    public function handle(XtreamController $xtream, MyChannelHlsService $hls): int
    {
        // ── Multicast / HTTP channel ingests ──
        $channels = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->get();

        foreach ($channels as $channel) {
            $outputDir = storage_path("app/streams/hls/{$channel->id}");

            if (! is_dir($outputDir)) {
                continue;
            }

            if (! $this->isStale($outputDir)) {
                continue;
            }

            $this->line("  - restarting stale ingest for channel {$channel->id} ({$channel->name})");

            $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;
            $xtream->ensureHlsStream(
                (int) $channel->id,
                $sourceUrl,
                $channel->program_number,
                $channel->local_address
            );
        }

        // ── Admin channel playouts ──
        $adminChannels = AdminChannel::where('is_active', true)->get();

        foreach ($adminChannels as $channel) {
            if ($this->isPlayoutAlive($channel)) {
                continue;
            }

            $this->line("  - restarting dead admin playout: {$channel->channel_name}");

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

        return self::SUCCESS;
    }

    private function isStale(string $outputDir): bool
    {
        $newest = 0;

        foreach (glob($outputDir . '/segment_*.ts') ?: [] as $segment) {
            $mtime = @filemtime($segment);
            if ($mtime !== false && $mtime > $newest) {
                $newest = $mtime;
            }
        }

        if ($newest === 0) {
            return false;
        }

        return (time() - $newest) > XtreamController::INGEST_STALE_SECONDS;
    }

    private function isPlayoutAlive(AdminChannel $channel): bool
    {
        $pid = cache()->get("mychannel_hls:{$channel->id}");

        if (! $pid) {
            return false;
        }

        if (! @file_exists("/proc/{$pid}")) {
            return false;
        }

        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");

        return $cmdline !== false && str_contains($cmdline, 'ffmpeg');
    }
}
