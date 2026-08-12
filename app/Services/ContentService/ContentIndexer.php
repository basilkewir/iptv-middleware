<?php

declare(strict_types=1);

namespace App\Services\ContentService;

use App\Contracts\Content\ContentIndexerInterface;
use App\Models\Content;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ContentIndexer implements ContentIndexerInterface
{
    private string $indexName;
    private int $cacheTtl = 3600;

    public function __construct()
    {
        $this->indexName = config('search.index_name', 'content');
    }

    public function indexContent(Content $content): void
    {
        $document = $this->buildDocument($content);

        $this->storeIndex($content->id, $document);

        Log::info('Content indexed', ['content_id' => $content->id]);
    }

    public function reindexContent(Content $content): void
    {
        $this->removeContent($content);
        $this->indexContent($content);

        Log::info('Content reindexed', ['content_id' => $content->id]);
    }

    public function removeContent(Content $content): void
    {
        Cache::forget("index:content:{$content->id}");

        $this->removeFromIndex($content->id);

        Log::info('Content removed from index', ['content_id' => $content->id]);
    }

    public function search(string $query, int $limit = 20): array
    {
        $results = [];

        $allContent = Cache::remember(
            'index:all_content',
            $this->cacheTtl,
            fn () => Content::where('is_active', true)->get()->toArray()
        );

        $query = strtolower($query);

        foreach ($allContent as $content) {
            $score = $this->calculateRelevanceScore($content, $query);

            if ($score > 0) {
                $results[] = array_merge($content, ['relevance_score' => $score]);
            }
        }

        usort($results, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($results, 0, $limit);
    }

    public function reindexAll(): int
    {
        $count = 0;

        $content = Content::where('is_active', true)->get();

        foreach ($content as $item) {
            $this->indexContent($item);
            $count++;
        }

        Log::info('Full reindex completed', ['count' => $count]);

        return $count;
    }

    public function getSuggestions(string $query, int $limit = 5): array
    {
        return Cache::remember(
            "index:suggestions:" . md5($query),
            $this->cacheTtl,
            function () use ($query, $limit) {
                $results = $this->search($query, $limit);

                return array_map(fn ($item) => [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'type' => $item['type'],
                    'thumbnail' => $item['thumbnail'],
                ], $results);
            }
        );
    }

    public function getTrendingContent(int $limit = 10): array
    {
        return Cache::remember(
            'index:trending',
            $this->cacheTtl,
            fn () => Content::where('is_active', true)
                ->orderBy('view_count', 'desc')
                ->limit($limit)
                ->get()
                ->toArray()
        );
    }

    public function getRelatedContent(int $contentId, int $limit = 5): array
    {
        return Cache::remember(
            "index:related:{$contentId}",
            $this->cacheTtl,
            function () use ($contentId, $limit) {
                $content = Content::find($contentId);

                if (!$content) {
                    return [];
                }

                return Content::where('id', '!=', $contentId)
                    ->where('is_active', true)
                    ->where(function ($query) use ($content) {
                        $query->where('type', $content->type)
                            ->orWhere('genre', $content->genre)
                            ->orWhere('category_id', $content->category_id);
                    })
                    ->orderBy('view_count', 'desc')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            }
        );
    }

    public function clearIndex(): void
    {
        Cache::forget('index:all_content');
        Cache::forget('index:trending');

        Log::info('Content index cleared');
    }

    public function getIndexStats(): array
    {
        return Cache::remember(
            'index:stats',
            $this->cacheTtl,
            function () {
                return [
                    'total_indexed' => Content::where('is_active', true)->count(),
                    'by_type' => Content::selectRaw('type, COUNT(*) as count')
                        ->where('is_active', true)
                        ->groupBy('type')
                        ->get()
                        ->pluck('count', 'type')
                        ->toArray(),
                    'last_updated' => now(),
                ];
            }
        );
    }

    private function buildDocument(Content $content): array
    {
        return [
            'id' => $content->id,
            'title' => $content->title,
            'description' => $content->description,
            'type' => $content->type,
            'genre' => $content->genre,
            'year' => $content->year,
            'rating' => $content->rating,
            'actors' => $content->actors,
            'director' => $content->director,
            'category_id' => $content->category_id,
            'thumbnail' => $content->thumbnail,
            'duration' => $content->duration,
            'view_count' => $content->view_count ?? 0,
            'is_active' => $content->is_active,
            'created_at' => $content->created_at,
            'searchable_text' => $this->buildSearchableText($content),
        ];
    }

    private function buildSearchableText(Content $content): string
    {
        $parts = [
            $content->title,
            $content->description,
            $content->genre,
            $content->actors,
            $content->director,
            (string) $content->year,
        ];

        return strtolower(implode(' ', array_filter($parts)));
    }

    private function storeIndex(int $contentId, array $document): void
    {
        Cache::put(
            "index:content:{$contentId}",
            $document,
            $this->cacheTtl
        );
    }

    private function removeFromIndex(int $contentId): void
    {
        Cache::forget("index:content:{$contentId}");
    }

    private function calculateRelevanceScore(array $content, string $query): float
    {
        $score = 0;
        $searchableText = $content['searchable_text'] ?? strtolower(implode(' ', [
            $content['title'] ?? '',
            $content['description'] ?? '',
            $content['genre'] ?? '',
        ]));

        if (str_contains($searchableText, $query)) {
            $score += 10;

            if (str_starts_with($searchableText, $query)) {
                $score += 5;
            }
        }

        $title = strtolower($content['title'] ?? '');
        if (str_contains($title, $query)) {
            $score += 8;

            if ($title === $query) {
                $score += 10;
            }
        }

        $genre = strtolower($content['genre'] ?? '');
        if (str_contains($genre, $query)) {
            $score += 3;
        }

        $actors = strtolower($content['actors'] ?? '');
        if (str_contains($actors, $query)) {
            $score += 4;
        }

        $director = strtolower($content['director'] ?? '');
        if (str_contains($director, $query)) {
            $score += 4;
        }

        $year = (string) ($content['year'] ?? '');
        if ($year === $query) {
            $score += 2;
        }

        $viewCount = $content['view_count'] ?? 0;
        $score += min(5, log($viewCount + 1));

        return $score;
    }
}
