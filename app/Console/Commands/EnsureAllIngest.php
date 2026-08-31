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

class EnsureAllIngest extends Command
{
    protected $signature = 'ingest:ensure-all';

    protected $description = 'Ensure all multicast readers and admin channel playouts are running. Safe to call repeatedly.';

    public function handle(
        MulticastIngestService $multicast,
        MyChannelHlsService $hls,
    ): int {
        $errors = 0;

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

        $this->line('── Admin channel playout ──');
        $adminChannels = AdminChannel::where('is_active', true)->get();

        foreach ($adminChannels as $channel) {
            if ($this->isPlayoutAlive($channel)) {
                if ($hls->isStalled($channel) && $channel->broadcast_status === 'live') {
                    $this->line("  RELAUNCH {$channel->channel_name} (stalled)");
                    $hls->restartKeepingSegments($channel);
                } else {
                    $this->line("  OK {$channel->channel_name}");
                }
                continue;
            }

            // Respect explicit stop: don't auto-start a channel the admin ended.
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
