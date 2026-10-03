<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AdminChannel\AdminChannel;
use App\Services\AdminChannel\MyChannelHlsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-apply prepared playlist items to a my-channel's LIVE playout after an edit.
 *
 * Refreshing only queues the next missing preparation; expensive FFmpeg
 * normalisation runs in PrepareMyChannelContent, outside the admin request.
 */
class RefreshMyChannelPlayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $channelId)
    {
    }

    public function handle(MyChannelHlsService $hls): void
    {
        $channel = AdminChannel::find($this->channelId);

        if (! $channel || ! $channel->is_my_channel) {
            return;
        }

        $hls->refreshPlaylist($channel);
    }
}
