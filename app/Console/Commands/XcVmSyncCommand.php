<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use App\Services\XcVm\XcVmSyncService;
use Illuminate\Console\Command;

/**
 * Push middleware entities into XC-VM.
 *
 * Examples:
 *   php artisan xcvm:sync                # full sync (categories → channels →
 *                                        # bouquets → lines → vod)
 *   php artisan xcvm:sync --type=channel # only live channels
 *   php artisan xcvm:sync --type=channel --id=12   # one channel
 *   php artisan xcvm:sync --delete --type=channel --id=12
 */
class XcVmSyncCommand extends Command
{
    protected $signature = 'xcvm:sync
                            {--type= : Entity type (category|channel|bouquet|user|vod) }
                            {--id= : Single entity id to sync }
                            {--delete : Delete the remote mirror of --id instead of syncing }
                            {--no-progress : Disable per-entity progress output }';

    protected $description = 'Sync middleware entities (channels, bouquets, users, VOD) to XC-VM';

    public function handle(XcVmSyncService $service): int
    {
        if (! $service->isEnabled()) {
            $this->error('XC-VM is not configured. Set XC_VM_ENABLED=true plus XC_VM_URL, XC_VM_ACCESS_CODE, XC_VM_API_KEY in .env.');

            return self::FAILURE;
        }

        $type = $this->option('type');
        $id = $this->option('id');

        if ($type && $id !== null) {
            return $this->handleSingle($service, (string) $type, (int) $id);
        }

        return $this->handleBulk($service, $type ? (array) $type : []);
    }

    private function handleSingle(XcVmSyncService $service, string $type, int $id): int
    {
        $progress = fn (SyncResult $result) => $this->renderResult($result);

        try {
            $result = $this->option('delete')
                ? $service->deleteEntity($type, $id)
                : $service->syncEntity($type, $id);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->renderResult($result);

        return $result->action === SyncResult::FAILED ? self::FAILURE : self::SUCCESS;
    }

    private function handleBulk(XcVmSyncService $service, array $only): int
    {
        $progress = fn (SyncResult $result) => $this->renderResult($result);

        $start = now();

        try {
            $reports = $service->syncAll($this->option('no-progress') ? null : $progress, $only);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $results = $service->allResults($reports);
        $summary = $this->summarize($reports);
        $seconds = (float) $start->diffInSeconds(now());

        $this->newLine();
        $this->table(
            ['Entity', 'Total', 'Created', 'Updated', 'Deleted', 'Skipped', 'Failed'],
            $summary
        );
        $this->line(sprintf('Elapsed: %.1fs', $seconds));

        if ($results->contains(fn (SyncResult $r) => $r->action === SyncResult::FAILED)) {
            $this->error('Sync completed with failures.');

            return self::FAILURE;
        }

        $this->info('Sync completed successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, SyncReport>  $reports
     * @return array<int, array<int|string>>
     */
    private function summarize(array $reports): array
    {
        $rows = [];

        foreach ($reports as $type => $report) {
            $s = $report->summary();
            $rows[] = [
                $type,
                $s['total'],
                $s['created'],
                $s['updated'],
                $s['deleted'],
                $s['skipped'],
                $s['failed'],
            ];
        }

        return $rows;
    }

    private function renderResult(SyncResult $result): void
    {
        $verb = match ($result->action) {
            SyncResult::CREATED => '<info>created</info>',
            SyncResult::UPDATED => '<comment>updated</comment>',
            SyncResult::DELETED => '<comment>deleted</comment>',
            SyncResult::SKIPPED => '<fg=cyan>skipped</fg=cyan>',
            default => '<error>failed</error>',
        };

        $detail = $result->label ?? "#{$result->entityId}";
        $suffix = $result->xcVmId !== null ? " (xc-vm #{$result->xcVmId})" : '';
        $error = $result->error ? " — {$result->error}" : '';

        $this->line(sprintf('[%s] %s #%d:%s%s', $result->entityType, $verb, $result->entityId, $detail, $error));
        unset($suffix);
    }
}