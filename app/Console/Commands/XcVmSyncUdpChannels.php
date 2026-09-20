<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\XcVm\XcVmStreamBridge;
use Illuminate\Console\Command;

/**
 * Sync all active channel source URLs to XC-VM.
 *
 * Ensures the streaming engine always has the current source URL for every
 * channel, including:
 *   - UDP/RTP multicast (loopback HLS from middleware ingest)
 *   - HTTP/HLS live feeds
 *   - RTMP sources
 *   - YouTube-resolved URLs
 *   - Flussonic re-streams
 *   - Live AdminChannels (My Channels)
 *
 * Scheduled every minute by the kernel (see Console/Kernel.php).
 */
class XcVmSyncUdpChannels extends Command
{
    protected $signature = 'xcvm:sync-udp
                            {--channel= : Sync a single channel by id}
                            {--type=channel : Entity type: channel, admin_channel}';

    protected $description = 'Push all active channel source URLs to XC-VM';

    public function handle(XcVmStreamBridge $bridge): int
    {
        $channelId = $this->option('channel');
        $type = $this->option('type');

        if ($channelId !== null) {
            if ($type === 'admin_channel') {
                $channel = \App\Models\AdminChannel\AdminChannel::find((int) $channelId);

                if (! $channel) {
                    $this->error("AdminChannel #{$channelId} not found.");
                    return self::FAILURE;
                }

                $updated = $bridge->syncAdminChannel($channel) ? 1 : 0;
                $this->line($updated ? "AdminChannel #{$channelId} pushed to XC-VM." : "AdminChannel #{$channelId} skipped (no fresh playlist or cooldown active).");
                return self::SUCCESS;
            }

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
        $this->line("Stream→XC-VM bridge: {$updated} channel(s) updated.");

        return self::SUCCESS;
    }
}
