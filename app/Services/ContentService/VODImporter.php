<?php

declare(strict_types=1);

namespace App\Services\ContentService;

use App\Contracts\Content\VODImporterInterface;
use App\Models\Content;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VODImporter implements VODImporterInterface
{
    private array $sources = [];
    private int $batchSize = 100;

    public function __construct()
    {
        $this->sources = config('content.vod_sources', []);
    }

    public function importFromSource(string $sourceName, array $options = []): array
    {
        $source = $this->getSource($sourceName);

        if (!$source) {
            throw new \InvalidArgumentException("VOD source '{$sourceName}' not found.");
        }

        Log::info('Starting VOD import', ['source' => $sourceName]);

        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $data = $this->fetchData($source, $options);

            foreach (array_chunk($data, $this->batchSize) as $batch) {
                $batchResults = $this->processBatch($batch, $sourceName);

                $results['imported'] += $batchResults['imported'];
                $results['updated'] += $batchResults['updated'];
                $results['skipped'] += $batchResults['skipped'];
                $results['errors'] = array_merge($results['errors'], $batchResults['errors']);
            }

            Log::info('VOD import completed', [
                'source' => $sourceName,
                'results' => $results,
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('VOD import failed', [
                'source' => $sourceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function importFromUrl(string $url, array $options = []): array
    {
        Log::info('Importing VOD from URL', ['url' => $url]);

        try {
            $response = Http::timeout(120)
                ->withHeaders(['User-Agent' => 'IPTV-Middleware/1.0'])
                ->get($url);

            if ($response->failed()) {
                throw new \RuntimeException("Failed to fetch data from URL: {$response->status()}");
            }

            $content = $response->body();

            return $this->parseAndImport($content, $options);
        } catch (\Exception $e) {
            Log::error('URL import failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function importFromFile(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        Log::info('Importing VOD from file', ['file' => $filePath]);

        $content = file_get_contents($filePath);

        return $this->parseAndImport($content, $options);
    }

    public function getAvailableSources(): array
    {
        return array_keys($this->sources);
    }

    public function getSourceInfo(string $sourceName): ?array
    {
        return $this->sources[$sourceName] ?? null;
    }

    public function scheduleImport(string $sourceName, string $schedule = 'daily'): void
    {
        Log::info('Scheduling VOD import', [
            'source' => $sourceName,
            'schedule' => $schedule,
        ]);

        $job = new \App\Jobs\ImportVODContent($sourceName);

        match ($schedule) {
            'hourly' => $job->everyHour(),
            'daily' => $job->daily(),
            'weekly' => $job->weekly(),
            default => $job->daily(),
        };
    }

    public function validateSource(string $sourceName): bool
    {
        $source = $this->getSource($sourceName);

        if (!$source) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->head($source['url']);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getImportHistory(string $sourceName, int $limit = 10): array
    {
        return \App\Models\ImportLog::where('source', $sourceName)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function getSource(string $sourceName): ?array
    {
        return $this->sources[$sourceName] ?? null;
    }

    private function fetchData(array $source, array $options): array
    {
        $url = $source['url'];
        $type = $source['type'] ?? 'json';

        $response = Http::timeout(120)
            ->withHeaders(['User-Agent' => 'IPTV-Middleware/1.0'])
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch data from source: {$response->status()}");
        }

        $content = $response->body();

        return match ($type) {
            'json' => json_decode($content, true) ?? [],
            'xml' => $this->parseXMLContent($content),
            'm3u' => $this->parseM3UContent($content),
            default => [],
        };
    }

    private function processBatch(array $batch, string $sourceName): array
    {
        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($batch as $item) {
            try {
                $result = $this->importItem($item, $sourceName);

                match ($result) {
                    'imported' => $results['imported']++,
                    'updated' => $results['updated']++,
                    'skipped' => $results['skipped']++,
                };
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'item' => $item['title'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function importItem(array $item, string $sourceName): string
    {
        $existingContent = Content::where('external_id', $item['id'] ?? null)
            ->where('source', $sourceName)
            ->first();

        if ($existingContent) {
            $existingContent->update([
                'title' => $item['title'] ?? $existingContent->title,
                'description' => $item['description'] ?? $existingContent->description,
                'source_url' => $item['url'] ?? $existingContent->source_url,
                'thumbnail' => $item['thumbnail'] ?? $existingContent->thumbnail,
                'duration' => $item['duration'] ?? $existingContent->duration,
                'metadata' => $item['metadata'] ?? $existingContent->metadata,
                'imported_at' => now(),
            ]);

            return 'updated';
        }

        Content::create([
            'external_id' => $item['id'] ?? null,
            'title' => $item['title'] ?? 'Untitled',
            'slug' => Str::slug($item['title'] ?? 'untitled'),
            'description' => $item['description'] ?? null,
            'type' => $item['type'] ?? 'movie',
            'category_id' => $this->resolveCategoryId($item['category'] ?? null),
            'source_url' => $item['url'] ?? null,
            'source' => $sourceName,
            'thumbnail' => $item['thumbnail'] ?? null,
            'duration' => $item['duration'] ?? null,
            'rating' => $item['rating'] ?? null,
            'year' => $item['year'] ?? null,
            'genre' => $item['genre'] ?? null,
            'actors' => $item['actors'] ?? null,
            'director' => $item['director'] ?? null,
            'metadata' => $item['metadata'] ?? null,
            'is_active' => true,
            'imported_at' => now(),
        ]);

        return 'imported';
    }

    private function resolveCategoryId(?string $categoryName): ?int
    {
        if (!$categoryName) {
            return null;
        }

        $category = Category::where('name', $categoryName)->first();

        if (!$category) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
            ]);
        }

        return $category->id;
    }

    private function parseXMLContent(string $content): array
    {
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            return [];
        }

        $items = [];

        foreach ($xml->channel as $channel) {
            $items[] = [
                'id' => (string) $channel['id'],
                'title' => (string) $channel->display-name,
                'thumbnail' => (string) ($channel->icon['src'] ?? ''),
            ];
        }

        return $items;
    }

    private function parseM3UContent(string $content): array
    {
        $lines = explode("\n", $content);
        $items = [];
        $currentItem = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#EXTINF:')) {
                preg_match('/#EXTINF:(-?\d+)\s+(.+?),/', $line, $matches);
                $currentItem = [
                    'duration' => $matches[1] ?? 0,
                    'title' => $matches[2] ?? 'Untitled',
                ];
            } elseif (!empty($line) && !str_starts_with($line, '#')) {
                $currentItem['url'] = $line;
                $currentItem['id'] = md5($line);
                $items[] = $currentItem;
                $currentItem = [];
            }
        }

        return $items;
    }

    private function parseAndImport(string $content, array $options): array
    {
        $type = $options['type'] ?? 'json';

        $data = match ($type) {
            'json' => json_decode($content, true) ?? [],
            'xml' => $this->parseXMLContent($content),
            'm3u' => $this->parseM3UContent($content),
            default => [],
        };

        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach (array_chunk($data, $this->batchSize) as $batch) {
            $batchResults = $this->processBatch($batch, $options['source'] ?? 'url_import');

            $results['imported'] += $batchResults['imported'];
            $results['updated'] += $batchResults['updated'];
            $results['skipped'] += $batchResults['skipped'];
            $results['errors'] = array_merge($results['errors'], $batchResults['errors']);
        }

        return $results;
    }
}
