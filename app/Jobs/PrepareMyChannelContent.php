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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            $this->releasePreparationLocks();
            return;
        }

        $entry = $content->playlistEntries()
            ->where('channel_id', $content->channel_id)
            ->first();

        $hls->prepareFile($content->channel, $content->id, storage_path('app/public/' . $content->file_path), $entry);
        $content->update(['prepared_at' => now()]);

        $this->releasePreparationLocks();
        Cache::forget('my-channel:prepare-failed:' . $content->id);

        // The item is baked now — fold it into the running playout so it joins
        // the loop without anyone pressing anything. This also queues the next
        // pending prepare, keeping the pipeline serialised.
        RefreshMyChannelPlayout::dispatch($content->channel_id);
    }

    public function failed(Throwable $exception): void
    {
        $this->releasePreparationLocks();

        $content = MyChannelContent::with('channel')->find($this->contentId);
        if (! $content || ! $content->channel?->is_my_channel) {
            return;
        }

        $message = $exception->getMessage() ?: 'Media preparation failed';
        app(MyChannelHlsService::class)->markPreparationFailed($content->id, $message);

        Log::error('My channel content preparation failed; continuing playlist queue', [
            'channel_id' => $content->channel_id,
            'content_id' => $content->id,
            'title'      => $content->title,
            'error'      => $message,
        ]);

        RefreshMyChannelPlayout::dispatch($content->channel_id);
    }

    private function releasePreparationLocks(): void
    {
        Cache::forget('my-channel:preparing:' . $this->contentId);
        Cache::forget(MyChannelHlsService::PREPARE_GATE);
    }
}