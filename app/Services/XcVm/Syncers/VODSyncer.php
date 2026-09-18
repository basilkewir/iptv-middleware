<?php

namespace App\Services\XcVm\Syncers;

use App\Models\VODContent;
use App\Models\VODMedia;
use App\Models\XcVmMapping;
use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use App\Services\XcVm\XcVmUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Pushes the middleware VOD library into XC-VM:
 *   - movies  -> create_movie / edit_movie
 *   - series  -> create_series / edit_series
 *   - episodes-> create_episode / edit_episode (from VODMedia rows)
 *
 * XC-VM serves movies and episodes on demand (there is no enable/disable
 * state for them), so only active content is created and toggling an item
 * to inactive removes its remote mirror.
 *
 * Mapping entity types: 'vod' (movie), 'series', 'episode' (VODMedia row).
 * The abstract runQueue is not reused because a single VODContent row maps to
 * either a movie or a series (two different mapping scopes) plus child
 * episodes, so every mapping lookup is explicit here.
 */
class VODSyncer extends AbstractSyncer
{
    public function entityType(): string
    {
        // Movies. Series and episodes are handled explicitly inside syncOne().
        return 'vod';
    }

    public function syncOne(int|Model $entity): SyncResult
    {
        $content = $entity instanceof VODContent
            ? $entity
            : VODContent::with(['categories', 'vodMedia'])->find((int) $entity);

        if (! $content) {
            return $this->skipped((int) $entity, 'vod content not found');
        }

        return $content->isSeries()
            ? $this->syncSeries($content)
            : $this->syncMovie($content);
    }

    public function queue(): Collection
    {
        return VODContent::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function syncAll(?callable $progress = null): SyncReport
    {
        $results = collect();

        foreach ($this->queue() as $id) {
            $results->push($this->syncOne((int) $id)->then($progress));
        }

        if (config('xcvm.prune_remote', false)) {
            $this->prune($results);
        }

        return $this->report($results);
    }

    public function delete(int $entityId): SyncResult
    {
        $content = VODContent::withTrashed()->with(['vodMedia'])->find($entityId);

        if (! $content) {
            return $this->skipped($entityId, 'vod content not found');
        }

        try {
            if ($content->isSeries()) {
                foreach ($content->vodMedia as $media) {
                    $this->deleteEpisode($media);
                }
                $this->deleteRemote('series', $entityId, $content->title);
            }

            return $this->deleteRemote('vod', $entityId, $content->title);
        } catch (Throwable $e) {
            return $this->failure($entityId, $e->getMessage());
        }
    }

    // ── Movies ──────────────────────────────────────────────────────────────

    private function syncMovie(VODContent $content): SyncResult
    {
        $label = (string) $content->title;

        if (! $content->is_active) {
            return $this->handleInactive('vod', (int) $content->id, $content->title, fn () => $this->client->deleteMovie($this->remoteIdOrFail('vod', (int) $content->id)));
        }

        $media = $content->vodMedia()->orderBy('id')->first();
        $streamUrl = $media ? XcVmUrl::vod($media->stream_url) : null;

        if ($streamUrl === null) {
            return $this->skipped((int) $content->id, 'movie has no stream url');
        }

        try {
            $categoryId = $this->remoteVodCategory($content, 'vod');

            $payload = [
                'name' => $label,
                'stream_url' => $streamUrl,
                'cover' => (string) ($content->poster_url ?? ''),
                'tmdb_id' => (int) ($content->tmdb_id ?: 0),
                'rating' => (float) $content->rating,
            ];

            if ($categoryId !== null) {
                $payload['category_id'] = $categoryId;
            }

            $remoteId = XcVmMapping::lookup('vod', (int) $content->id);

            if ($remoteId !== null) {
                $this->client->editMovie($remoteId, [
                    'name' => $label,
                    'stream_url' => $streamUrl,
                ]);
                $action = SyncResult::UPDATED;
            } else {
                $remoteId = $this->client->createMovie($payload);

                if (! $remoteId) {
                    return $this->failure((int) $content->id, 'XC-VM returned no movie id', $label);
                }

                $action = SyncResult::CREATED;
            }

            $this->rememberMapping('vod', (int) $content->id, $remoteId, ['stream_url' => $streamUrl]);

            return new SyncResult('vod', (int) $content->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $content->id, $e->getMessage(), $label);
        }
    }

    // ── Series + episodes ───────────────────────────────────────────────────

    private function syncSeries(VODContent $content): SyncResult
    {
        $label = (string) $content->title;

        try {
            $categoryId = $this->remoteVodCategory($content, 'series');

            $payload = [
                'name' => $label,
                'cover' => (string) ($content->poster_url ?? ''),
                'tmdb_id' => (int) ($content->tmdb_id ?: 0),
            ];

            if ($categoryId !== null) {
                $payload['category_id'] = $categoryId;
            }

            $remoteId = XcVmMapping::lookup('series', (int) $content->id);

            if ($remoteId !== null) {
                $this->client->editSeries($remoteId, ['name' => $label]);
                $action = SyncResult::UPDATED;
            } else {
                $remoteId = $this->client->createSeries($payload);

                if (! $remoteId) {
                    return $this->failure((int) $content->id, 'XC-VM returned no series id', $label);
                }

                $action = SyncResult::CREATED;
            }

            $this->rememberMapping('series', (int) $content->id, $remoteId);

            $results = $this->syncEpisodes($content, $remoteId);

            if ($results->contains(fn (SyncResult $r) => $r->action === SyncResult::FAILED)) {
                $firstFailure = $results->first(fn (SyncResult $r) => $r->action === SyncResult::FAILED);

                return new SyncResult('series', (int) $content->id, SyncResult::FAILED, $remoteId, $label, $firstFailure->error);
            }

            return new SyncResult('series', (int) $content->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $content->id, $e->getMessage(), $label);
        }
    }

    /**
     * @return Collection<int, SyncResult>
     */
    private function syncEpisodes(VODContent $content, int $seriesRemoteId): Collection
    {
        $results = collect();

        foreach ($content->vodMedia()->orderBy('season_number')->orderBy('episode_number')->get() as $media) {
            $results->push($this->syncEpisode($media, $seriesRemoteId));
        }

        return $results;
    }

    private function syncEpisode(VODMedia $media, int $seriesRemoteId): SyncResult
    {
        $label = $media->episode_title ?: "S{$media->season_number}E{$media->episode_number}";

        $streamUrl = XcVmUrl::vod($media->stream_url);

        if ($streamUrl === null || ! $media->is_available) {
            return $this->skipped((int) $media->id, 'episode has no stream url');
        }

        try {
            $remoteId = XcVmMapping::lookup('episode', (int) $media->id);

            if ($remoteId !== null) {
                $this->client->editEpisode($remoteId, [
                    'title' => $label,
                    'stream_url' => $streamUrl,
                ]);
                $action = SyncResult::UPDATED;
            } else {
                $remoteId = $this->client->createEpisode([
                    'series' => $seriesRemoteId,
                    'season_num' => (int) $media->season_number,
                    'episode' => (int) $media->episode_number,
                    'title' => $label,
                    'stream_source' => $streamUrl,
                    'duration' => $this->durationString($media->duration),
                    'plot' => (string) ($media->episode_title ?: ''),
                ]);

                if (! $remoteId) {
                    return $this->failure((int) $media->id, 'XC-VM returned no episode id', $label);
                }

                $action = SyncResult::CREATED;
            }

            $this->rememberMapping('episode', (int) $media->id, $remoteId, ['stream_url' => $streamUrl]);

            return new SyncResult('episode', (int) $media->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $media->id, $e->getMessage(), $label);
        }
    }

    private function deleteEpisode(VODMedia $media): void
    {
        $remoteId = XcVmMapping::lookup('episode', (int) $media->id);

        if ($remoteId === null) {
            return;
        }

        $this->client->deleteEpisode($remoteId);
        XcVmMapping::forget('episode', (int) $media->id);
    }

    private function deleteRemote(string $type, int $entityId, string $label): SyncResult
    {
        $remoteId = XcVmMapping::lookup($type, $entityId);

        if ($remoteId === null) {
            return $this->skipped($entityId, 'no remote mapping');
        }

        try {
            if ($type === 'vod') {
                $this->client->deleteMovie($remoteId);
            } else {
                $this->client->deleteSeries($remoteId);
            }

            XcVmMapping::forget($type, $entityId);

            return new SyncResult($type, $entityId, SyncResult::DELETED, null, $label);
        } catch (Throwable $e) {
            return new SyncResult($type, $entityId, SyncResult::FAILED, null, $label, $e->getMessage());
        }
    }

    // ── Shared helpers ──────────────────────────────────────────────────────

    private function remoteVodCategory(VODContent $content, string $expectedType): ?int
    {
        $category = $content->categories()->first();

        if (! $category) {
            return null;
        }

        $rawType = strtolower((string) $category->category_type);
        $isType = match ($expectedType) {
            'series' => in_array($rawType, ['series', 'show', 'shows', 'tv', 'tv_show']),
            default => in_array($rawType, ['vod', 'movie', 'movies']),
        };

        if (! $isType || ! $category->is_active) {
            return null;
        }

        return XcVmMapping::lookup('category', (int) $category->id);
    }

    private function rememberMapping(string $type, int $entityId, int $xcVmId, array $meta = []): void
    {
        XcVmMapping::remember($type, $entityId, $xcVmId, $meta);
    }

    private function durationString(?int $seconds): string
    {
        if (! $seconds || $seconds <= 0) {
            return '';
        }

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }

    /**
     * Remove orphans for all three VOD mapping scopes.
     */
    protected function prune(Collection $results): void
    {
        if (! config('xcvm.prune_remote', false)) {
            return;
        }

        foreach (['vod', 'series', 'episode'] as $type) {
            $ids = match ($type) {
                'vod', 'series' => VODContent::query()->pluck('id'),
                default => VODMedia::query()->pluck('id'),
            };
            $localIds = $ids->map(fn ($id) => (int) $id)->all();

            $orphans = XcVmMapping::where('entity_type', $type)
                ->whereNotIn('entity_id', $localIds)
                ->get();

            foreach ($orphans as $mapping) {
                try {
                    if ($type === 'episode') {
                        $this->client->deleteEpisode((int) $mapping->xc_vm_id);
                        XcVmMapping::forget('episode', (int) $mapping->entity_id);
                        $results->push(new SyncResult('episode', (int) $mapping->entity_id, SyncResult::DELETED));
                    } else {
                        $results->push($this->deleteRemote($type, (int) $mapping->entity_id, ''));
                    }
                } catch (Throwable $e) {
                    $results->push(new SyncResult($type, (int) $mapping->entity_id, SyncResult::FAILED, null, null, $e->getMessage()));
                }
            }
        }
    }
}