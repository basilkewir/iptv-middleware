<?php

namespace App\Observers\XcVm;

use App\Models\Channel;

class ChannelObserver
{
    use DispatchesXcVmSync;

    protected function entityType(): string
    {
        return 'channel';
    }

    public function created(Channel $channel): void
    {
        $this->queueUpsert($channel);
    }

    public function updated(Channel $channel): void
    {
        $this->queueUpsert($channel);
    }

    public function restored(Channel $channel): void
    {
        $this->queueUpsert($channel);
    }

    public function deleted(Channel $channel): void
    {
        $this->queueDelete($channel);
    }
}