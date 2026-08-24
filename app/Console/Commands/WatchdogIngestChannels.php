<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\XtreamController;
use App\Models\Channel;
use Illuminate\Console\Command;

class WatchdogIngestChannels extends Command
{
    protected $signature = 'channels:watchdog';

    protected $description = 'Restart any HLS ingest that has stopped writing new segments';

    public function handle(XtreamController $xtream): int
    {
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

        // No segments means the ingest hasn't started yet — not our job here
        if ($newest === 0) {
            return false;
        }

        return (time() - $newest) > XtreamController::INGEST_STALE_SECONDS;
    }
}
