<?php

namespace App\Observers\XcVm;

use App\Models\Bouquet;

class BouquetObserver
{
    use DispatchesXcVmSync;

    protected function entityType(): string
    {
        return 'bouquet';
    }

    public function created(Bouquet $bouquet): void
    {
        $this->queueUpsert($bouquet);
    }

    public function updated(Bouquet $bouquet): void
    {
        $this->queueUpsert($bouquet);
    }

    public function restored(Bouquet $bouquet): void
    {
        $this->queueUpsert($bouquet);
    }

    public function deleted(Bouquet $bouquet): void
    {
        $this->queueDelete($bouquet);
    }
}