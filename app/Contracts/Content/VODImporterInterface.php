<?php

declare(strict_types=1);

namespace App\Contracts\Content;

interface VODImporterInterface
{
    public function importFromSource(string $sourceName, array $options = []): array;

    public function importFromUrl(string $url, array $options = []): array;

    public function importFromFile(string $filePath, array $options = []): array;

    public function getAvailableSources(): array;

    public function getSourceInfo(string $sourceName): ?array;

    public function scheduleImport(string $sourceName, string $schedule = 'daily'): void;

    public function validateSource(string $sourceName): bool;

    public function getImportHistory(string $sourceName, int $limit = 10): array;
}
