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
 * Re-apply a my-channel's playlist to its LIVE playout after an edit.
 *
 * Runs on the queue because refreshing may synchronously re-prepare new
 * items (an ffmpeg normalisation pass each) — far too long for the admin
 * HTTP request that triggered the edit.
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
