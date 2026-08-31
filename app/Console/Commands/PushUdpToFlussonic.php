<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamingService\FlussonicService;
use Illuminate\Console\Command;

class PushUdpToFlussonic extends Command
{
    protected $signature = 'channels:push-udp-to-flussonic
                            {--kill-ffmpeg : Kill existing UDP ffmpeg ingest processes after pushing}';

    protected $description = 'Push all active UDP/RTP channels to Flussonic and update active_stream_url to Flussonic HLS';

    public function handle(FlussonicService $flussonic): int
    {
        if (! $flussonic->isReachable()) {
            $this->error('Flussonic API is not reachable at ' . config('streaming.flussonic.url'));
            return self::FAILURE;
        }

        $channels = Channel::where('is_active', true)
            ->where(function ($q) {
                $q->where('stream_type', 'udp')
                  ->orWhere('stream_url', 'like', 'udp://%')
                  ->orWhere('stream_url', 'like', 'rtp://%');
            })
            ->get();

        if ($channels->isEmpty()) {
            $this->info('No active UDP/RTP channels found.');
            return self::SUCCESS;
        }

        $this->info("Pushing {$channels->count()} UDP channel(s) to Flussonic...");

        $pushed = 0;
        $failed = 0;

        foreach ($channels as $channel) {
            $programNumber = $channel->program_number ?? 0;
            $streamName = 'ch-' . $channel->id . '-p' . $programNumber;
            $udpUrl     = $channel->stream_url;

            try {
                $hlsUrl = $flussonic->ensureStream(
                    $streamName,
                    $udpUrl,
                    $channel->program_number,
                    $channel->local_address
                );

                // Store Flussonic HLS URL as active_stream_url so the
                // middleware serves it via ffmpeg copy (HTTP→HLS path),
                // while stream_url keeps the original udp:// for reference.
                $channel->update(['active_stream_url' => $hlsUrl]);

                $this->line("  ✓ {$streamName} {$channel->name} → {$hlsUrl}");
                $pushed++;
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$streamName} {$channel->name}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Done: {$pushed} pushed, {$failed} failed.");

        if ($this->option('kill-ffmpeg')) {
            $this->info('Killing UDP ffmpeg ingest processes...');
            $killed = 0;
            foreach ($channels as $channel) {
                $outputDir = storage_path("app/streams/hls/{$channel->id}");
                // Write .stop so the wrapper loop exits cleanly on next iteration.
                @file_put_contents($outputDir . '/.stop', '1');
                $pidFile = $outputDir . '/ingest.pid';
                if (is_file($pidFile)) {
                    $pid = (int) trim((string) file_get_contents($pidFile));
                    if ($pid > 0) {
                        @exec("kill -TERM -{$pid} 2>/dev/null");
                        usleep(200000);
                        @exec("kill -KILL -{$pid} 2>/dev/null");
                        @unlink($pidFile);
                        $killed++;
                    }
                }
                $marker = 'streams/hls/' . $channel->id . '/playlist.m3u8';
                @exec('pkill -KILL -f ' . escapeshellarg($marker) . ' 2>/dev/null');
            }
            // Also kill any ffmpeg still reading from a udp:// or rtp:// URL directly.
            @exec('pkill -KILL -f "ffmpeg.*udp://" 2>/dev/null');
            @exec('pkill -KILL -f "ffmpeg.*rtp://" 2>/dev/null');
            $this->info("Killed {$killed} ingest wrapper(s).");
        }

        return self::SUCCESS;
    }
}
