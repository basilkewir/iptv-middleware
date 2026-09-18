<?php

namespace App\Services\XcVm;

use App\Services\XcVm\Exceptions\XcVmException;
use App\Services\XcVm\Syncers\AbstractSyncer;
use App\Services\XcVm\Syncers\BouquetSyncer;
use App\Services\XcVm\Syncers\CategorySyncer;
use App\Services\XcVm\Syncers\ChannelSyncer;
use App\Services\XcVm\Syncers\LineSyncer;
use App\Services\XcVm\Syncers\VODSyncer;
use Illuminate\Support\Collection;

/**
 * Orchestrates the individual syncers into a single dependency-ordered push:
 *
 *   categories -> channels -> bouquets -> lines -> vod
 *
 * Bouquets reference XC-VM channel ids and lines reference bouquet ids, so
 * the order above guarantees every parent is mapped before its children.
 */
class XcVmSyncService
{
    public function __construct(protected readonly XcVmClient $client)
    {
    }

    public function isEnabled(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array<int, SyncReport>
     */
    public function syncAll(?callable $progress = null, array $only = []): array
    {
        $this->assertEnabled();

        $reports = [];

        $stages = [
            'category' => fn () => $this->categorySyncer(),
            'channel' => fn () => $this->channelSyncer(),
            'bouquet' => fn () => $this->bouquetSyncer(),
            'user' => fn () => $this->lineSyncer(),
            'vod' => fn () => $this->vodSyncer(),
        ];

        foreach ($stages as $type => $factory) {
            if ($only !== [] && ! in_array($type, $only, true)) {
                continue;
            }

            $syncer = $factory();
            $reports[$type] = $syncer->syncAll($progress);
        }

        return $reports;
    }

    public function syncEntity(string $type, int $id): SyncResult
    {
        $this->assertEnabled();

        return $this->syncerFor($type)->syncOne($id);
    }

    public function deleteEntity(string $type, int $id): SyncResult
    {
        $this->assertEnabled();

        return $this->syncerFor($type)->delete($id);
    }

    /**
     * Aggregate every result across all syncers (used by xcvm:sync output).
     *
     * @return Collection<int, SyncResult>
     */
    public function allResults(array $reports): Collection
    {
        $results = collect();

        foreach ($reports as $report) {
            $report->results->each(fn (SyncResult $r) => $results->push($r));
        }

        return $results;
    }

    public function testConnection(): array
    {
        return [
            'configured' => $this->isEnabled(),
            'url' => $this->isEnabled() ? $this->client->baseUrl() : null,
            'diagnostics' => $this->client->diagnostics(),
        ];
    }

    protected function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new XcVmException(
                'XC-VM is not configured. Set XC_VM_ENABLED=true plus XC_VM_URL, XC_VM_ACCESS_CODE and XC_VM_API_KEY in .env.'
            );
        }
    }

    /**
     * @return array<string, class-string<AbstractSyncer>>
     */
    protected function syncerMap(): array
    {
        return [
            'category' => CategorySyncer::class,
            'channel' => ChannelSyncer::class,
            'bouquet' => BouquetSyncer::class,
            'user' => LineSyncer::class,
            'line' => LineSyncer::class,
            'vod' => VODSyncer::class,
            'movie' => VODSyncer::class,
            'series' => VODSyncer::class,
            'episode' => VODSyncer::class,
        ];
    }

    protected function syncerFor(string $type): AbstractSyncer
    {
        $class = $this->syncerMap()[strtolower($type)]
            ?? throw new XcVmException("Unknown XC-VM entity type [{$type}].");

        return new $class($this->client);
    }

    protected function categorySyncer(): CategorySyncer
    {
        return new CategorySyncer($this->client);
    }

    protected function channelSyncer(): ChannelSyncer
    {
        return new ChannelSyncer($this->client);
    }

    protected function bouquetSyncer(): BouquetSyncer
    {
        return new BouquetSyncer($this->client);
    }

    protected function lineSyncer(): LineSyncer
    {
        return new LineSyncer($this->client);
    }

    protected function vodSyncer(): VODSyncer
    {
        return new VODSyncer($this->client);
    }
}