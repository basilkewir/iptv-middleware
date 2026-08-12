<?php

declare(strict_types=1);

namespace App\Services\ContentService;

use App\Contracts\Content\ContentManagerInterface;
use App\Models\Content;
use App\Models\Category;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContentManager implements ContentManagerInterface
{
    private VODImporter $vodImporter;
    private ContentIndexer $indexer;

    private const CACHE_TTL = 3600;
    private const PER_PAGE = 20;

    public function __construct(VODImporter $vodImporter, ContentIndexer $indexer)
    {
        $this->vodImporter = $vodImporter;
        $this->indexer = $indexer;
    }

    public function getAllContent(array $filters = [], int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $query = Content::query();

        $query = $this->applyFilters($query, $filters);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function getContentById(int $id): ?array
    {
        return Cache::remember(
            "content:{$id}",
            self::CACHE_TTL,
            fn () => Content::find($id)?->toArray()
        );
    }

    public function createContent(array $data): Content
    {
        $content = Content::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'movie',
            'category_id' => $data['category_id'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'duration' => $data['duration'] ?? null,
            'rating' => $data['rating'] ?? null,
            'year' => $data['year'] ?? null,
            'genre' => $data['genre'] ?? null,
            'actors' => $data['actors'] ?? null,
            'director' => $data['director'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->indexer->indexContent($content);

        Cache::forget("content:{$content->id}");

        Log::info('Content created', [
            'content_id' => $content->id,
            'title' => $content->title,
        ]);

        return $content;
    }

    public function updateContent(Content $content, array $data): Content
    {
        $content->update($data);

        $this->indexer->reindexContent($content);

        Cache::forget("content:{$content->id}");

        Log::info('Content updated', ['content_id' => $content->id]);

        return $content;
    }

    public function deleteContent(Content $content): void
    {
        $this->indexer->removeContent($content);

        $content->delete();

        Cache::forget("content:{$content->id}");

        Log::info('Content deleted', ['content_id' => $content->id]);
    }

    public function searchContent(string $query, int $limit = 20): array
    {
        return Cache::remember(
            "content:search:" . md5($query),
            self::CACHE_TTL,
            fn () => $this->indexer->search($query, $limit)
        );
    }

    public function getContentByCategory(int $categoryId, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        return Cache::remember(
            "content:category:{$categoryId}:page:{$page}",
            self::CACHE_TTL,
            function () use ($categoryId, $page, $perPage) {
                $query = Content::where('category_id', $categoryId);

                $total = $query->count();
                $items = $query->orderBy('created_at', 'desc')
                    ->offset(($page - 1) * $perPage)
                    ->limit($perPage)
                    ->get()
                    ->toArray();

                return [
                    'items' => $items,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                ];
            }
        );
    }

    public function getContentByType(string $type, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        return Cache::remember(
            "content:type:{$type}:page:{$page}",
            self::CACHE_TTL,
            function () use ($type, $page, $perPage) {
                $query = Content::where('type', $type);

                $total = $query->count();
                $items = $query->orderBy('created_at', 'desc')
                    ->offset(($page - 1) * $perPage)
                    ->limit($perPage)
                    ->get()
                    ->toArray();

                return [
                    'items' => $items,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                ];
            }
        );
    }

    public function getPopularContent(int $limit = 10): array
    {
        return Cache::remember(
            "content:popular:{$limit}",
            self::CACHE_TTL,
            fn () => Content::where('is_active', true)
                ->orderBy('view_count', 'desc')
                ->limit($limit)
                ->get()
                ->toArray()
        );
    }

    public function getRecentContent(int $limit = 10): array
    {
        return Cache::remember(
            "content:recent:{$limit}",
            self::CACHE_TTL,
            fn () => Content::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray()
        );
    }

    public function toggleContentStatus(Content $content): Content
    {
        $content->update([
            'is_active' => !$content->is_active,
        ]);

        Cache::forget("content:{$content->id}");

        return $content;
    }

    public function getContentViewStats(int $contentId): array
    {
        $content = Content::find($contentId);

        if (!$content) {
            return [];
        }

        return [
            'content_id' => $content->id,
            'view_count' => $content->view_count ?? 0,
            'total_watch_time' => $content->total_watch_time ?? 0,
            'avg_completion_rate' => $content->avg_completion_rate ?? 0,
        ];
    }

    public function incrementViewCount(int $contentId): void
    {
        Content::where('id', $contentId)->increment('view_count');

        Cache::forget("content:{$contentId}");
    }

    public function getContentStats(): array
    {
        return Cache::remember(
            'content:stats',
            self::CACHE_TTL,
            function () {
                return [
                    'total_content' => Content::count(),
                    'active_content' => Content::where('is_active', true)->count(),
                    'by_type' => Content::selectRaw('type, COUNT(*) as count')
                        ->groupBy('type')
                        ->get()
                        ->pluck('count', 'type')
                        ->toArray(),
                    'total_views' => Content::sum('view_count'),
                ];
            }
        );
    }

    public function syncContentCategories(Content $content, array $categoryIds): void
    {
        $content->categories()->sync($categoryIds);

        Cache::forget("content:{$content->id}");
    }

    private function applyFilters($query, array $filters)
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (isset($filters['genre'])) {
            $query->where('genre', 'LIKE', "%{$filters['genre']}%");
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('description', 'LIKE', "%{$filters['search']}%");
            });
        }

        return $query;
    }
}
