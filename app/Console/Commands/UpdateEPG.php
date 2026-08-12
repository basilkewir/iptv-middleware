<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EPGService\EPGManager;
use App\Models\EPGSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateEPG extends Command
{
    protected $signature = 'epg:update
                            {--source= : Update from specific source}
                            {--clean : Clean expired programs after update}
                            {--days=7 : Days of programs to keep}
                            {--dry-run : Simulate without updating}';

    protected $description = 'Update EPG data from configured sources';

    private EPGManager $epgManager;

    public function __construct(EPGManager $epgManager)
    {
        parent::__construct();
        $this->epgManager = $epgManager;
    }

    public function handle(): int
    {
        $source = $this->option('source');
        $clean = $this->option('clean');
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info('Starting EPG update...');

        $sources = $this->getSources($source);

        if ($sources->isEmpty()) {
            $this->warn('No EPG sources found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$sources->count()} EPG source(s) to process.");

        $totalUpdated = 0;
        $failed = 0;

        foreach ($sources as $epgSource) {
            try {
                $this->line("Processing: {$epgSource->name} ({$epgSource->url})");

                if ($dryRun) {
                    $this->info("  [DRY RUN] Would update EPG from {$epgSource->url}");
                    $totalUpdated++;
                    continue;
                }

                $updated = $this->epgManager->updateEPGData($epgSource->url);

                $epgSource->update([
                    'last_updated' => now(),
                    'last_count' => $updated,
                ]);

                $this->info("  Updated {$updated} programs");
                $totalUpdated += $updated;
            } catch (\Exception $e) {
                $this->error("  Failed to update from {$epgSource->name}: {$e->getMessage()}");
                $failed++;

                Log::error('EPG update failed', [
                    'source' => $epgSource->name,
                    'url' => $epgSource->url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($clean && !$dryRun) {
            $this->info('Cleaning expired programs...');
            $deleted = $this->epgManager->deleteExpiredPrograms($days);
            $this->info("Deleted {$deleted} expired programs.");
        }

        $this->info("EPG update completed. Updated: {$totalUpdated}, Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function getSources(?string $sourceName): \Illuminate\Database\Eloquent\Collection
    {
        $query = EPGSource::where('is_active', true);

        if ($sourceName) {
            $query->where('name', $sourceName);
        }

        return $query->get();
    }
}
