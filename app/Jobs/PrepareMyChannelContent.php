<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AdminChannel\MyChannelContent;
use App\Services\AdminChannel\MyChannelHlsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrepareMyChannelContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $contentId)
    {
    }

    public function handle(MyChannelHlsService $hls): void
    {
        $content = MyChannelContent::with('channel')->find($this->contentId);

        if (! $content || ! $content->channel?->is_my_channel) {
            return;
        }

        $entry = $content->playlistEntries()
            ->where('channel_id', $content->channel_id)
            ->first();

        try {
            $hls->prepareFile($content->channel, $content->id, storage_path('app/public/' . $content->file_path), $entry);

            $content->update(['prepared_at' => now()]);
        } finally {
            // Always release both locks, including on failure, or the item
            // could never be queued again and the box-wide gate would stay
            // shut for good.
            $cache = \Illuminate\Support\Facades\Cache::forget(
                'my-channel:preparing:' . $content->id
            );
            \Illuminate\Support\Facades\Cache::forget(MyChannelHlsService::PREPARE_GATE);
        }

        // The item is baked now — fold it into the running playout so it joins
        // the loop without anyone pressing anything. This also queues the next
        // pending prepare, keeping the pipeline serialised.
        RefreshMyChannelPlayout::dispatch($content->channel_id);
    }
}