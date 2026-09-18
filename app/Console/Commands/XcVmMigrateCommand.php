<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\User;
use App\Models\VODContent;
use App\Models\XcVmMapping;
use App\Services\XcVm\SyncResult;
use App\Services\XcVm\XcVmSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot migration: push every existing middleware entity into XC-VM.
 *
 * Run once after install.sh has wired up XC-VM, or any time you want to
 * re-sync everything from scratch (e.g. after a XC-VM database reset).
 *
 * Usage:
 *   php artisan xcvm:migrate
 *   php artisan xcvm:migrate --fresh          # wipe all mappings first
 *   php artisan xcvm:migrate --type=channel   # only channels
 *   php artisan xcvm:migrate --dry-run        # count rows, no API calls
 */
class XcVmMigrateCommand extends Command
{
    protected $signature = 'xcvm:migrate
                            {--type= : Limit to one entity type (category|channel|bouquet|user|vod)}
                            {--fresh : Delete all existing xc_vm_mappings before migrating}
                            {--dry-run : Count entities without making any API calls}
                            {--no-progress : Suppress per-entity output}';

    protected $description = 'Migrate all existing middleware data (channels, VOD, users…) into XC-VM';

    public function handle(XcVmSyncService $service): int
    {
        if (! $service->isEnabled()) {
            $this->error('XC-VM is not configured. Set XC_VM_ENABLED=true and XC_VM_* credentials in .env.');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        if ($this->option('fresh')) {
            $type = $this->option('type');
            $deleted = $type
                ? XcVmMapping::where('entity_type', $type)->delete()
                : XcVmMapping::truncate();
            $this->warn("Cleared {$deleted} existing mapping(s).");
        }

        $only = $this->option('type') ? [$this->option('type')] : [];

        $this->info('Starting XC-VM migration…');
        $this->newLine();

        $progress = $this->option('no-progress')
            ? null
            : fn (SyncResult $r) => $this->renderResult($r);

        $start = now();

        try {
            $reports = $service->syncAll($progress, $only);
        } catch (\Throwable $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $results = $service->allResults($reports);
        $elapsed = round($start->diffInSeconds(now()), 1);

        $this->newLine();
        $this->table(
            ['Entity', 'Total', 'Created', 'Updated', 'Skipped', 'Failed'],
            collect($reports)->map(fn ($report, $type) => array_merge(
                [$type],
                array_values(array_intersect_key(
                    $report->summary(),
                    array_flip(['total', 'created', 'updated', 'skipped', 'failed'])
                ))
            ))->values()->all()
        );

        $this->line("Elapsed: {$elapsed}s");

        $failures = $results->filter(fn (SyncResult $r) => $r->action === SyncResult::FAILED);

        if ($failures->isNotEmpty()) {
            $this->newLine();
            $this->warn("{$failures->count()} entity(ies) failed to migrate:");
            foreach ($failures as $r) {
                $this->line("  [{$r->entityType}] #{$r->entityId} — {$r->error}");
            }
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Migration complete. All data is now in XC-VM.');
        return self::SUCCESS;
    }

    private function dryRun(): int
    {
        $type = $this->option('type');

        $counts = [
            'category' => ContentCategory::where('is_active', true)->count(),
            'channel'  => Channel::count(),
            'bouquet'  => DB::table('bouquets')->where('is_active', true)->count(),
            'user'     => User::where('is_admin', false)->where('is_reseller', false)->count(),
            'vod'      => VODContent::where('is_active', true)->count(),
        ];

        if ($type) {
            $counts = array_intersect_key($counts, [$type => true]);
        }

        $mapped = XcVmMapping::selectRaw('entity_type, count(*) as cnt')
            ->groupBy('entity_type')
            ->pluck('cnt', 'entity_type');

        $this->table(
            ['Entity', 'Local rows', 'Already mapped', 'Will migrate'],
            collect($counts)->map(fn ($total, $t) => [
                $t,
                $total,
                $mapped[$t] ?? 0,
                max(0, $total - ($mapped[$t] ?? 0)),
            ])->values()->all()
        );

        return self::SUCCESS;
    }

    private function renderResult(SyncResult $result): void
    {
        $verb = match ($result->action) {
            SyncResult::CREATED => '<info>created</info>',
            SyncResult::UPDATED => '<comment>updated</comment>',
            SyncResult::DELETED => '<comment>deleted</comment>',
            SyncResult::SKIPPED => '<fg=cyan>skipped</fg=cyan>',
            default             => '<error>failed</error>',
        };

        $label = $result->label ? " \"{$result->label}\"" : '';
        $error = $result->error ? " — {$result->error}" : '';

        $this->line(sprintf(
            '  [%s] %s #%d%s%s',
            $result->entityType,
            $verb,
            $result->entityId,
            $label,
            $error
        ));
    }
}
