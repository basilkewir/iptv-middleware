<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\XtreamController;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IngestAllChannels extends Command
{
    protected $signature = 'channels:ingest-all';

    protected $description = 'Start local HLS ingest for every active channel so all channels are streamed by this middleware to the local network at the same time';

    public function handle(XtreamController $xtream): int
    {
        $channels = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->orderBy('channel_number')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels to ingest.');
            return self::SUCCESS;
        }

        $activeIds = $channels->pluck('id')->map(fn ($id) => (string) $id)->all();

        foreach ($channels as $channel) {
            $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

            // UDP/RTP channels are ingested by Flussonic — skip local ffmpeg.
            // active_stream_url will already point to Flussonic's HLS output
            // after channels:push-udp-to-flussonic has run.
            $rawUrl = $channel->stream_url ?? '';
            if (str_starts_with($rawUrl, 'udp://') || str_starts_with($rawUrl, 'rtp://')) {
                $this->line(sprintf(
                    '  - channel %d (#%s) %s [skipped — Flussonic]',
                    $channel->id,
                    $channel->channel_number,
                    $channel->name ?: '(no name)'
                ));
                continue;
            }

            $xtream->ensureHlsStream(
                (int) $channel->id,
                $sourceUrl,
                $channel->program_number,
                $channel->local_address,
                (bool) ($channel->transcoding_enabled ?? false)
            );
            $this->line(sprintf(
                '  - channel %d (#%s) %s',
                $channel->id,
                $channel->channel_number,
                $channel->name ?: '(no name)'
            ));
        }

        $this->cleanupOrphanedIngests($activeIds);

        $this->info(sprintf('Ensured HLS ingest for %d active channel(s).', $channels->count()));

        return self::SUCCESS;
    }

    /**
     * Remove HLS output directories that no longer belong to an active
     * channel. Otherwise deleted/deactivated channels keep being served from
     * their last stale playlist, which clients end up looping on.
     */
    private function cleanupOrphanedIngests(array $activeIds): void
    {
        $hlsRoot = storage_path('app/streams/hls');

        if (! is_dir($hlsRoot)) {
            return;
        }

        foreach (glob($hlsRoot . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $id = basename($dir);

            if (! ctype_digit($id) || in_array($id, $activeIds, true)) {
                continue;
            }

            // UDP/RTP channels are written by the shared multicast group
            // reader whose ffmpeg command line contains every member's
            // output path — pkill-ing by this directory marker would kill
            // the entire group. Skip process-killing for those.
            $legacy = Channel::find((int) $id);

            if ($legacy && (str_starts_with((string) $legacy->stream_url, 'udp://') || str_starts_with((string) $legacy->stream_url, 'rtp://'))) {
                continue;
            }

            $pidFile = $dir . '/ingest.pid';

            if (is_file($pidFile)) {
                $pid = (int) trim((string) file_get_contents($pidFile));

                if ($pid > 0) {
                    @exec("kill -TERM -{$pid} 2>/dev/null");
                    usleep(500000);
                    @exec("kill -KILL -{$pid} 2>/dev/null");
                }
            }

            $marker = 'streams/hls/' . $id . '/playlist.m3u8';
            @exec('pkill -TERM -f ' . escapeshellarg($marker) . ' 2>/dev/null');
            usleep(500000);
            @exec('pkill -KILL -f ' . escapeshellarg($marker) . ' 2>/dev/null');

            if (File::deleteDirectory($dir)) {
                $this->line("  - cleaned orphaned HLS ingest for channel {$id}");
            }
        }
    }
}
