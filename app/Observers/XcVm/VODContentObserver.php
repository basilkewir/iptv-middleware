<?php

namespace App\Observers\XcVm;

use App\Models\VODContent;

class VODContentObserver
{
    use DispatchesXcVmSync;

    protected function entityType(): string
    {
        // VODSyncer branches internally on movie vs series, so the parent row
        // type is always 'vod'.
        return 'vod';
    }

    public function created(VODContent $content): void
    {
        $this->queueUpsert($content);
    }

    public function updated(VODContent $content): void
    {
        $this->queueUpsert($content);
    }

    public function restored(VODContent $content): void
    {
        $this->queueUpsert($content);
    }

    public function deleted(VODContent $content): void
    {
        $this->queueDelete($content);
    }
}