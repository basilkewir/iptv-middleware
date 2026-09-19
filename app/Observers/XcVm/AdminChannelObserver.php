<?php

namespace App\Observers\XcVm;

use App\Models\AdminChannel\AdminChannel;

class AdminChannelObserver
{
    use DispatchesXcVmSync;

    protected function entityType(): string
    {
        return 'admin_channel';
    }

    public function created(AdminChannel $channel): void
    {
        $this->queueUpsert($channel);
    }

    public function updated(AdminChannel $channel): void
    {
        $this->queueUpsert($channel);
    }

    public function restored(AdminChannel $channel): void
    {
        $this->queueUpsert($channel);
    }

    public function deleted(AdminChannel $channel): void
    {
        $this->queueDelete($channel);
    }
}
