<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ContentService\VODImporter;
use App\Models\ContentSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportVODContent extends Command
{
    protected $signature = 'content:import-vod
                            {--source= : Import from specific source}
                            {--url= : Import from URL}
                            {--file= : Import from file}
                            {--type=json : Content type (json, xml, m3u)}
                            {--dry-run : Simulate without importing}';

    protected $description = 'Import VOD content from external sources';

    private VODImporter $vodImporter;

    public function __construct(VODImporter $vodImporter)
    {
        parent::__construct();
        $this->vodImporter = $vodImporter;
    }

    public function handle(): int
    {
        $source = $this->option('source');
        $url = $this->option('url');
        $file = $this->option('file');
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        $this->info('Starting VOD content import...');

        try {
            $results = $this->performImport($source, $url, $file, $type, $dryRun);

            $this->displayResults($results);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");

            Log::error('VOD import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    private function performImport(?string $source, ?string $url, ?string $file, string $type, bool $dryRun): array
    {
        if ($dryRun) {
            $this->info('[DRY RUN] Simulating import...');
            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
                'dry_run' => true,
            ];
        }

        if ($source) {
            return $this->importFromSource($source);
        }

        if ($url) {
            return $this->importFromUrl($url, $type);
        }

        if ($file) {
            return $this->importFromFile($file, $type);
        }

        return $this->importFromAllSources();
    }

    private function importFromSource(string $sourceName): array
    {
        $this->info("Importing from source: {$sourceName}");

        return $this->vodImporter->importFromSource($sourceName);
    }

    private function importFromUrl(string $url, string $type): array
    {
        $this->info("Importing from URL: {$url}");

        return $this->vodImporter->importFromUrl($url, ['type' => $type]);
    }

    private function importFromFile(string $file, string $type): array
    {
        $this->info("Importing from file: {$file}");

        return $this->vodImporter->importFromFile($file, ['type' => $type]);
    }

    private function importFromAllSources(): array
    {
        $this->info('Importing from all active sources...');

        $sources = ContentSource::where('is_active', true)->get();

        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($sources as $source) {
            try {
                $this->line("Processing: {$source->name}");

                $sourceResults = $this->vodImporter->importFromSource($source->name);

                $results['imported'] += $sourceResults['imported'];
                $results['updated'] += $sourceResults['updated'];
                $results['skipped'] += $sourceResults['skipped'];
                $results['errors'] = array_merge($results['errors'], $sourceResults['errors']);

                $source->update(['last_imported_at' => now()]);

                $this->info("  Imported: {$sourceResults['imported']}, Updated: {$sourceResults['updated']}");
            } catch (\Exception $e) {
                $this->error("  Failed to import from {$source->name}: {$e->getMessage()}");
                $results['errors'][] = [
                    'source' => $source->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function displayResults(array $results): void
    {
        $this->info('');
        $this->info('Import Results:');
        $this->line("  Imported: {$results['imported']}");
        $this->line("  Updated: {$results['updated']}");
        $this->line("  Skipped: {$results['skipped']}");

        if (!empty($results['errors'])) {
            $this->warn("  Errors: " . count($results['errors']));

            foreach ($results['errors'] as $error) {
                $this->error("    - " . ($error['item'] ?? $error['source'] ?? 'Unknown') . ": {$error['error']}");
            }
        }

        if ($results['imported'] > 0) {
            $this->info('Content index will be updated automatically.');
        }
    }
}
