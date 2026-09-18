<?php

namespace App\Services\XcVm\Syncers;

use App\Models\XcVmMapping;
use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use App\Services\XcVm\XcVmClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

abstract class AbstractSyncer
{
    public function __construct(protected readonly XcVmClient $client)
    {
    }

    /**
     * The local entity type key used in the xc_vm_mappings table.
     */
    abstract public function entityType(): string;

    /**
     * Push a single entity to XC-VM (create when no mapping exists, else edit).
     */
    abstract public function syncOne(int|Model $entity): SyncResult;

    /**
     * All local ids of this entity type (for full sync).
     *
     * @return Collection<int, int>
     */
    abstract public function queue(): Collection;

    /**
     * Delete the remote mirror of a local entity.
     */
    abstract public function delete(int $entityId): SyncResult;

    /**
     * @return Collection<int, SyncResult>
     */
    protected function runQueue(?callable $progress = null, bool $prune = false): Collection
    {
        $results = collect();

        foreach ($this->queue() as $id) {
            $results->push($this->syncOne((int) $id)->then($progress));
        }

        if ($prune) {
            $this->prune($results);
        }

        return $results;
    }

    /**
     * Remove remote mirror objects whose local entity no longer exists.
     */
    protected function prune(Collection $results): void
    {
        if (! config('xcvm.prune_remote', false)) {
            return;
        }

        $localIds = $this->queue()->map(fn ($id) => (int) $id)->all();

        $orphans = XcVmMapping::where('entity_type', $this->entityType())
            ->whereNotIn('entity_id', $localIds)
            ->get();

        foreach ($orphans as $mapping) {
            try {
                $result = $this->delete((int) $mapping->entity_id);
                $results->push($result);
            } catch (Throwable $e) {
                $results->push($this->failure((int) $mapping->entity_id, $e->getMessage()));
            }
        }
    }

    protected function created(int $entityId, int $xcVmId, ?string $label = null): SyncResult
    {
        return new SyncResult($this->entityType(), $entityId, SyncResult::CREATED, $xcVmId, $label);
    }

    protected function updated(int $entityId, int $xcVmId, ?string $label = null): SyncResult
    {
        return new SyncResult($this->entityType(), $entityId, SyncResult::UPDATED, $xcVmId, $label);
    }

    protected function deleted(int $entityId, ?string $label = null): SyncResult
    {
        return new SyncResult($this->entityType(), $entityId, SyncResult::DELETED, null, $label);
    }

    protected function skipped(int $entityId, ?string $label = null): SyncResult
    {
        return new SyncResult($this->entityType(), $entityId, SyncResult::SKIPPED, null, $label);
    }

    protected function failure(int $entityId, string $error, ?string $label = null): SyncResult
    {
        return new SyncResult($this->entityType(), $entityId, SyncResult::FAILED, null, $label, $error);
    }

    protected function remoteId(int $entityId): ?int
    {
        return XcVmMapping::lookup($this->entityType(), $entityId);
    }

    protected function remember(int $entityId, int $xcVmId, array $meta = []): void
    {
        XcVmMapping::remember($this->entityType(), $entityId, $xcVmId, $meta);
    }

    protected function forget(int $entityId): void
    {
        XcVmMapping::forget($this->entityType(), $entityId);
    }

    protected function report(Collection $results): SyncReport
    {
        return new SyncReport($results);
    }
}