<?php

namespace App\Observers\XcVm;

use App\Jobs\XcVmSyncJob;
use App\Models\VODMedia;

/**
 * Refreshes the parent VOD row whenever a media file changes. Editing an
 * episode simply re-pushes the whole series (movie or series), which keeps the
 * child episode ids in sync; orphan removal is left to the full-sync prune.
 */
class VODMediaObserver
{
    public function created(VODMedia $media): void
    {
        $this->queueRefresh($media);
    }

    public function updated(VODMedia $media): void
    {
        $this->queueRefresh($media);
    }

    public function restored(VODMedia $media): void
    {
        $this->queueRefresh($media);
    }

    public function deleted(VODMedia $media): void
    {
        if (! config('xcvm.live_sync', true) || ! $media->vod_content_id) {
            return;
        }

        XcVmSyncJob::dispatch('vod', (int) $media->vod_content_id, 'upsert');
    }

    private function queueRefresh(VODMedia $media): void
    {
        if (! config('xcvm.live_sync', true) || ! $media->vod_content_id) {
            return;
        }

        XcVmSyncJob::dispatch('vod', (int) $media->vod_content_id, 'upsert');
    }
}