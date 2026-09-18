<?php

namespace App\Jobs;

use App\Services\XcVm\SyncResult;
use App\Services\XcVm\XcVmSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background push of a single entity to XC-VM (live sync).
 *
 * Unique per (operation, type, id) so rapid admin edits collapse into one
 * pending job instead of flooding the queue. Failures are logged, never
 * re-queued infinitely; the scheduled full resync reconciles later.
 */
class XcVmSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly string $type,
        public readonly int $entityId,
        public readonly string $operation = 'upsert',
    ) {
    }

    public function uniqueId(): string
    {
        return "xcvm:{$this->operation}:{$this->type}:{$this->entityId}";
    }

    public function handle(XcVmSyncService $service): void
    {
        if (! $service->isEnabled()) {
            return;
        }

        try {
            $result = $this->operation === 'delete'
                ? $service->deleteEntity($this->type, $this->entityId)
                : $service->syncEntity($this->type, $this->entityId);

            if ($result->action === SyncResult::FAILED) {
                Log::warning('XC-VM live sync failed', $result->toArray());
            }
        } catch (Throwable $e) {
            Log::error('XC-VM live sync threw an exception', [
                'type' => $this->type,
                'entity_id' => $this->entityId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}