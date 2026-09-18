<?php

namespace App\Services\XcVm\Syncers;

use App\Models\Bouquet;
use App\Models\XcVmMapping;
use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class BouquetSyncer extends AbstractSyncer
{
    public function entityType(): string
    {
        return 'bouquet';
    }

    public function syncOne(int|Model $entity): SyncResult
    {
        $bouquet = $entity instanceof Bouquet ? $entity : Bouquet::find((int) $entity);
        if (! $bouquet) {
            return $this->skipped((int) $entity, 'bouquet not found');
        }

        if (! $bouquet->is_active) {
            return $this->skipInactive($bouquet);
        }

        $label = (string) $bouquet->name;

        try {
            $channelIds = $bouquet->channels()
                ->where('is_active', true)
                ->orderBy('bouquet_channels.sort_order')
                ->orderBy('bouquet_channels.channel_id')
                ->pluck('channels.id');

            $remoteChannelIds = $channelIds
                ->map(fn ($id) => $this->remoteIdFor('channel', (int) $id))
                ->filter()
                ->values()
                ->all();

            $remoteId = $this->remoteId((int) $bouquet->id);

            if ($remoteId !== null) {
                $this->client->editBouquet($remoteId, $label, $remoteChannelIds);
                $action = SyncResult::UPDATED;
            } else {
                $remoteId = $this->client->createBouquet($label, $remoteChannelIds);

                if (! $remoteId) {
                    return $this->failure((int) $bouquet->id, 'XC-VM returned no bouquet id', $label);
                }

                $action = SyncResult::CREATED;
            }

            $this->remember((int) $bouquet->id, $remoteId, [
                'channel_count' => count($remoteChannelIds),
            ]);

            return new SyncResult($this->entityType(), (int) $bouquet->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $bouquet->id, $e->getMessage(), $label);
        }
    }

    public function delete(int $entityId): SyncResult
    {
        $mapping = XcVmMapping::where('entity_type', $this->entityType())
            ->where('entity_id', $entityId)
            ->first();

        if (! $mapping) {
            return $this->skipped($entityId, 'no remote mapping');
        }

        try {
            $this->client->deleteBouquet((int) $mapping->xc_vm_id);
            $this->forget($entityId);

            return $this->deleted($entityId);
        } catch (Throwable $e) {
            return $this->failure($entityId, $e->getMessage());
        }
    }

    public function queue(): Collection
    {
        return Bouquet::query()
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function syncAll(?callable $progress = null): SyncReport
    {
        $results = $this->runQueue($progress, config('xcvm.prune_remote', false));

        return $this->report($results);
    }

    private function skipInactive(Bouquet $bouquet): SyncResult
    {
        // If a remote mirror exists, delete it so users stop seeing the package.
        $remoteId = $this->remoteId((int) $bouquet->id);
        if ($remoteId !== null) {
            try {
                $this->client->deleteBouquet($remoteId);
                $this->forget((int) $bouquet->id);

                return $this->deleted((int) $bouquet->id);
            } catch (Throwable $e) {
                return $this->failure((int) $bouquet->id, $e->getMessage());
            }
        }

        return $this->skipped((int) $bouquet->id, 'inactive');
    }

    private function remoteIdFor(string $entityType, int $entityId): ?int
    {
        return XcVmMapping::lookup($entityType, $entityId);
    }
}