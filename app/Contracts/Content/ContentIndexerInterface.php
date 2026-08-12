<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\Models\Content;

interface ContentIndexerInterface
{
    public function indexContent(Content $content): void;

    public function reindexContent(Content $content): void;

    public function removeContent(Content $content): void;

    public function search(string $query, int $limit = 20): array;

    public function reindexAll(): int;

    public function getSuggestions(string $query, int $limit = 5): array;

    public function getTrendingContent(int $limit = 10): array;

    public function getRelatedContent(int $contentId, int $limit = 5): array;

    public function clearIndex(): void;

    public function getIndexStats(): array;
}
