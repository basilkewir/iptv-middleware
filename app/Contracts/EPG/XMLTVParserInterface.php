<?php

declare(strict_types=1);

namespace App\Contracts\EPG;

interface XMLTVParserInterface
{
    public function parse(string $content): array;

    public function parseChannels(string $content): array;

    public function parseProgramme(string $content, array $channels = []): array;

    public function parseDate(string $dateString): ?string;

    public function validateXMLTV(string $content): bool;

    public function getChannelIdMapping(string $content): array;
}
