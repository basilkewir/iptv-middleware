<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\XtreamController;
use App\Models\Channel;
use App\Services\StreamingService\MulticastIngestService;
use Illuminate\Console\Command;

class RefreshStream extends Command
{
    protected $signature = 'stream:refresh
                            {channel : Channel ID or slug}
                            {--force : Kill and restart even if segments are fresh}';

    protected $description = 'Restart the ingest for a single channel without touching any other stream';

    public function handle(XtreamController $xtream, MulticastIngestService $multicast): int
    {
        $identifier = $this->argument('channel');

        $channel = is_numeric($identifier)
            ? Channel::where('id', $identifier)->where('is_active', true)->first()
            : Channel::where('slug', $identifier)->where('is_active', true)->first();

        if (! $channel) {
            $this->error("Channel '{$identifier}' not found or inactive.");
            return self::FAILURE;
        }

        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        if (empty($sourceUrl)) {
            $this->error("Channel {$channel->id} ({$channel->name}) has no stream URL.");
            return self::FAILURE;
        }

        $outputDir = storage_path("app/streams/hls/{$channel->id}");
        $pidFile   = $outputDir . '/ingest.pid';

        $this->line("Refreshing channel {$channel->id} — {$channel->name}");
        $this->line("  Source: {$sourceUrl}");

        // Kill the existing ingest process for this channel only
        if (is_file($pidFile)) {
            $pid = (int) trim((string) file_get_contents($pidFile));

            if ($pid > 0) {
                $isMulticast = str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://');

                if ($isMulticast) {
                    // For multicast, stop the whole group reader — it will be
                    // restarted by ensureGroupReader() below covering all channels
                    // in the group. We only stop the group, not other groups.
                    $multicast->stopGroup($channel);
                    $this->line("  Stopped multicast group reader (PID {$pid})");
                } else {
                    // HTTP/RTMP: kill only this channel's wrapper + ffmpeg child
                    @exec("kill -TERM -{$pid} 2>/dev/null");
                    usleep(600000);
                    @exec("kill -KILL -{$pid} 2>/dev/null");

                    $marker = 'streams/hls/' . $channel->id . '/playlist.m3u8';
                    @exec('pkill -KILL -f ' . escapeshellarg($marker) . ' 2>/dev/null');

                    @unlink($pidFile);
                    $this->line("  Killed ingest process (PID {$pid})");
                }
            }
        }

        // Clear restart backoff so ensureHlsStream() doesn't block the respawn
        cache()->forget("ffmpeg:last_restart:{$channel->id}");

        // Wipe stale segments so clients get a clean start
        foreach (glob($outputDir . '/segment_*.ts') ?: [] as $seg) {
            @unlink($seg);
        }
        foreach (glob($outputDir . '/playlist*.m3u8') ?: [] as $pl) {
            @unlink($pl);
        }

        // Respawn — delegates to multicast group reader if applicable
        $xtream->ensureHlsStream(
            (int) $channel->id,
            $sourceUrl,
            $channel->program_number,
            $channel->local_address,
            (bool) ($channel->transcoding_enabled ?? false)
        );

        $this->info("  Restarted. Waiting for first segment...");

        // Wait up to 15s for the first segment to appear
        $deadline = time() + 15;
        while (time() < $deadline) {
            $segs = glob($outputDir . '/segment_*.ts') ?: [];
            if (! empty($segs)) {
                $this->info("  ✓ First segment written — stream is live.");
                return self::SUCCESS;
            }
            sleep(1);
        }

        $this->warn("  Stream started but no segment written yet (source may be offline).");
        return self::SUCCESS;
    }
}
