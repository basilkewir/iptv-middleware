<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\XcVm\UdpXcVmBridge;
use Illuminate\Console\Command;

/**
 * Sync UDP/RTP multicast channel HLS outputs to XC-VM.
 *
 * The middleware reads UDP streams via FFmpeg and writes HLS playlists to
 * storage/app/streams/hls/{id}/. This command pushes the loopback HLS URL
 * for each active multicast channel to XC-VM so the engine serves the feed
 * without ever needing to join the multicast group itself.
 *
 * Scheduled every minute by the kernel (see Console/Kernel.php).
 */
class XcVmSyncUdpChannels extends Command
{
    protected $signature = 'xcvm:sync-udp
                            {--channel= : Sync a single channel by id}';

    protected $description = 'Push UDP/RTP multicast HLS outputs to XC-VM stream sources';

    public function handle(UdpXcVmBridge $bridge): int
    {
        $channelId = $this->option('channel');

        if ($channelId !== null) {
            $channel = \App\Models\Channel::find((int) $channelId);

            if (! $channel) {
                $this->error("Channel #{$channelId} not found.");
                return self::FAILURE;
            }

            $updated = $bridge->syncChannel($channel) ? 1 : 0;
            $this->line($updated ? "Channel #{$channelId} pushed to XC-VM." : "Channel #{$channelId} skipped (no fresh playlist or cooldown active).");
            return self::SUCCESS;
        }

        $updated = $bridge->syncAll();
        $this->line("UDP→XC-VM bridge: {$updated} channel(s) updated.");

        return self::SUCCESS;
    }
}
