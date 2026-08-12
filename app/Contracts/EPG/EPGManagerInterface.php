<?php

declare(strict_types=1);

namespace App\Contracts\EPG;

interface EPGManagerInterface
{
    public function fetchEPG(string $url): array;

    public function getProgramsForChannel(int $channelId, ?string $date = null): array;

    public function getCurrentProgram(int $channelId): ?array;

    public function getProgramsByTimeRange(string $startTime, string $endTime, ?int $channelId = null): array;

    public function updateEPGData(string $url): int;

    public function deleteExpiredPrograms(int $daysToKeep = 7): int;

    public function getEPGStats(): array;

    public function searchPrograms(string $query): array;

    public function getChannelEPG(int $channelId, int $limit = 50): array;

    public function importFromXMLTV(string $content): int;

    public function clearCache(): void;

    public function getNextProgram(int $channelId): ?array;

    public function getPrimeTimePrograms(int $channelId, ?string $date = null): array;
}
