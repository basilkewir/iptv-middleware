<?php

namespace App\Services\XcVm\Syncers;

use App\Models\ContentCategory;
use App\Models\XcVmMapping;
use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class CategorySyncer extends AbstractSyncer
{
    public function entityType(): string
    {
        return 'category';
    }

    public function syncOne(int|Model $entity): SyncResult
    {
        $category = $entity instanceof ContentCategory
            ? $entity
            : ContentCategory::find((int) $entity);

        if (! $category) {
            return $this->skipped((int) $entity, 'category not found');
        }

        $label = (string) $category->name;

        // Category-only / generic groups that XC-VM has no bucket for are skipped.
        $rawType = strtolower((string) $category->category_type);
        if ($rawType === '' || $rawType === 'general') {
            return $this->skipped((int) $category->id, 'generic category type, not synced');
        }

        $type = $this->mapType($rawType);

        try {
            $parentId = 0;
            if ($category->parent_id && $category->parent_id !== $category->id) {
                $parentId = $this->categoryRemoteId((int) $category->parent_id) ?? 0;
            }

            $remoteId = $this->remoteId((int) $category->id);

            if ($remoteId !== null) {
                $this->client->editCategory($remoteId, $label, $parentId);
                $action = SyncResult::UPDATED;
            } else {
                $remoteId = $this->client->createCategory($label, $type, $parentId);

                if (! $remoteId) {
                    return $this->failure((int) $category->id, 'XC-VM returned no category id', $label);
                }

                $action = SyncResult::CREATED;
            }

            $this->remember((int) $category->id, $remoteId, ['category_type' => $type]);

            return new SyncResult($this->entityType(), (int) $category->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $category->id, $e->getMessage(), $label);
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
            $this->client->deleteCategory((int) $mapping->xc_vm_id);
            $this->forget($entityId);

            return $this->deleted($entityId);
        } catch (Throwable $e) {
            return $this->failure($entityId, $e->getMessage());
        }
    }

    public function queue(): Collection
    {
        return ContentCategory::query()
            ->where('is_active', true)
            ->whereNotNull('category_type')
            ->where('category_type', '!=', 'general')
            ->orderBy('parent_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function syncAll(?callable $progress = null): SyncReport
    {
        $results = $this->runQueue($progress, config('xcvm.prune_remote', false));

        return $this->report($results);
    }

    /**
     * Translate a middleware category_type into an XC-VM category_type.
     */
    private function mapType(string $type): string
    {
        return match ($type) {
            'vod', 'movie', 'movies' => 'vod',
            'series', 'show', 'shows' => 'series',
            'radio', 'audio' => 'radio',
            default => 'live',
        };
    }

    private function categoryRemoteId(int $categoryId): ?int
    {
        return XcVmMapping::lookup($this->entityType(), $categoryId);
    }
}