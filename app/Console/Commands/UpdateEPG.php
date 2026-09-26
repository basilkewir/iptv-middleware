<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EPGService\EPGManager;
use App\Models\EPGSource;
use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateEPG extends Command
{
    protected $signature = 'epg:update
                            {--source= : Update from specific source name}
                            {--from-config : Also process config-defined sources (creates/updates epg_sources rows)}
                            {--clean : Clean expired programs after update}
                            {--days=7 : Days of expired programs to keep}
                            {--dry-run : Simulate without updating}';

    protected $description = 'Fetch EPG data from open-source XMLTV feeds and store in database';

    public function __construct(
        private EPGManager $epgManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceName = $this->option('source');
        $fromConfig = $this->option('from-config');
        $clean = $this->option('clean');
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info('EPG Update — starting');

        // Optionally sync config-defined sources into the DB
        if ($fromConfig) {
            $this->syncConfigSources();
        }

        $sources = $this->getSources($sourceName);

        if ($sources->isEmpty()) {
            $this->warn('No active EPG sources found. Use --from-config to seed from config/epg.php or add sources in Admin > EPG.');
            return Command::SUCCESS;
        }

        $this->info("Found {$sources->count()} active source(s) to process.");

        $totalUpdated = 0;
        $totalFailed = 0;

        foreach ($sources as $epgSource) {
            $this->line("  → {$epgSource->name}");

            if ($dryRun) {
                $this->info("    [DRY RUN] Would fetch from {$epgSource->url}");
                continue;
            }

            try {
                $updated = $this->epgManager->updateEPGData($epgSource->url);

                $epgSource->update([
                    'last_fetched_at' => now(),
                ]);

                $this->info("    Imported {$updated} programs");
                $totalUpdated += $updated;
            } catch (\Exception $e) {
                $this->error("    FAILED: {$e->getMessage()}");
                $totalFailed++;

                Log::error('EPG source update failed', [
                    'source' => $epgSource->name,
                    'url'    => $epgSource->url,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if ($clean && !$dryRun) {
            $this->line("Cleaning programs older than {$days} days...");
            $deleted = $this->epgManager->deleteExpiredPrograms($days);
            $this->info("  Deleted {$deleted} expired programs.");
        }

        $this->newLine();
        $this->info("Done. Programs imported: {$totalUpdated}, Sources failed: {$totalFailed}");

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function syncConfigSources(): void
    {
        $configSources = config('epg.sources', []);

        $synced = 0;

        foreach ($configSources as $key => $cfg) {
            $name = $cfg['name'] ?? $key;
            $url = $cfg['url'] ?? null;
            $type = $cfg['type'] ?? 'xmltv';
            $enabled = $cfg['enabled'] ?? true;

            if (!$url) {
                continue;
            }

            EPGSource::updateOrCreate(
                ['url' => $url],
                [
                    'name'            => $name,
                    'type'            => $type,
                    'is_active'       => $enabled,
                    'update_interval' => 14400,
                ]
            );

            $synced++;
        }

        $this->info("  Synced {$synced} source(s) from config/epg.php");
    }

    private function getSources(?string $sourceName): \Illuminate\Database\Eloquent\Collection
    {
        $query = EPGSource::where('is_active', true);

        if ($sourceName) {
            $query->where('name', 'LIKE', "%{$sourceName}%");
        }

        return $query->get();
    }
}
