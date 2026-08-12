<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\Models\Content;

interface ContentManagerInterface
{
    public function getAllContent(array $filters = [], int $page = 1, int $perPage = 20): array;

    public function getContentById(int $id): ?array;

    public function createContent(array $data): Content;

    public function updateContent(Content $content, array $data): Content;

    public function deleteContent(Content $content): void;

    public function searchContent(string $query, int $limit = 20): array;

    public function getContentByCategory(int $categoryId, int $page = 1, int $perPage = 20): array;

    public function getContentByType(string $type, int $page = 1, int $perPage = 20): array;

    public function getPopularContent(int $limit = 10): array;

    public function getRecentContent(int $limit = 10): array;

    public function toggleContentStatus(Content $content): Content;

    public function getContentViewStats(int $contentId): array;

    public function incrementViewCount(int $contentId): void;

    public function getContentStats(): array;

    public function syncContentCategories(Content $content, array $categoryIds): void;
}
