<?php

namespace App\Observers\XcVm;

use App\Jobs\XcVmSyncJob;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues live-sync jobs for any model change. Observers decide the entity
 * type + id; the job is deduplicated by (operation, type, id) automatically.
 */
trait DispatchesXcVmSync
{
    protected function queueUpsert(Model $model): void
    {
        if (! config('xcvm.live_sync', true)) {
            return;
        }

        XcVmSyncJob::dispatch($this->entityType(), (int) $model->getKey(), 'upsert');
    }

    protected function queueDelete(Model $model): void
    {
        if (! config('xcvm.live_sync', true)) {
            return;
        }

        XcVmSyncJob::dispatch($this->entityType(), (int) $model->getKey(), 'delete');
    }
}