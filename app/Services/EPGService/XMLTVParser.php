<?php

declare(strict_types=1);

namespace App\Services\EPGService;

use App\Contracts\EPG\XMLTVParserInterface;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;

class XMLTVParser implements XMLTVParserInterface
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s';

    /**
     * Parse full XMLTV content and return channels + programmes.
     *
     * Accepts raw XML content (may be gzipped).
     */
    public function parse(string $content): array
    {
        try {
            $content = $this->decompressIfGzipped($content);

            libxml_use_internal_errors(true);

            $xml = simplexml_load_string($content);

            if ($xml === false) {
                $errors = libxml_get_errors();
                $errorMessage = $errors ? $errors[0]->message : 'Unknown XML parsing error';
                throw new \RuntimeException("Failed to parse XMLTV: {$errorMessage}");
            }

            $channels = $this->parseChannels($xml);
            $programs = $this->parseProgramme($xml, $channels);

            Log::info('XMLTV parsed successfully', [
                'channels' => count($channels),
                'programs' => count($programs),
            ]);

            libxml_clear_errors();

            return [
                'channels' => $channels,
                'programs' => $programs,
            ];
        } catch (\Exception $e) {
            Log::error('XMLTV parsing failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Parse <channel> elements from XMLTV.
     */
    public function parseChannels(string $content): array
    {
        $content = $this->decompressIfGzipped($content);

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            return [];
        }

        $channels = [];

        if (isset($xml->channel)) {
            foreach ($xml->channel as $channel) {
                $channelId = (string) $channel['id'];
                $name = (string) $channel->display-name;

                $icon = null;
                if (isset($channel->icon)) {
                    $icon = (string) $channel->icon['src'];
                }

                $language = null;
                if (isset($channel->language)) {
                    $language = (string) $channel->language;
                }

                $channels[$channelId] = [
                    'id'       => $channelId,
                    'name'     => $name,
                    'icon'     => $icon,
                    'language' => $language,
                ];
            }
        }

        libxml_clear_errors();

        return $channels;
    }

    /**
     * Parse <programme> elements from XMLTV.
     */
    public function parseProgramme(string $content, array $channels = []): array
    {
        if (!is_string($content)) {
            return [];
        }

        $content = $this->decompressIfGzipped($content);

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            return [];
        }

        $programs = [];

        if (isset($xml->programme)) {
            foreach ($xml->programme as $programme) {
                $program = $this->parseProgrammeElement($programme, $channels);

                if ($program) {
                    $programs[] = $program;
                }
            }
        }

        libxml_clear_errors();

        return $programs;
    }

    public function parseDate(string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            $date = \DateTime::createFromFormat('YmdHis O', $dateString);

            if ($date === false) {
                $date = \DateTime::createFromFormat('YmdHis', $dateString);
            }

            if ($date === false) {
                return null;
            }

            return $date->format(self::DATE_FORMAT);
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function validateXMLTV(string $content): bool
    {
        $content = $this->decompressIfGzipped($content);

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            return false;
        }

        $errors = libxml_get_errors();

        libxml_clear_errors();

        return empty($errors);
    }

    /**
     * Get a mapping of XMLTV channel IDs to database channel IDs.
     *
     * Matches channels.epg_channel_id (primary) or channels.epg_id (fallback)
     * against the XMLTV channel IDs found in the content.
     */
    public function getChannelIdMapping(string $content): array
    {
        $channels = $this->parseChannels($content);

        $dbChannels = Channel::where('is_active', true)
            ->whereNotNull('epg_channel_id')
            ->orWhereNotNull('epg_id')
            ->get(['id', 'epg_channel_id', 'epg_id']);

        $mapping = [];

        foreach ($channels as $channelId => $channelData) {
            // Primary match: epg_channel_id
            $dbChannel = $dbChannels->first(fn($c) => $c->epg_channel_id === $channelId);

            // Fallback match: epg_id
            if (!$dbChannel) {
                $dbChannel = $dbChannels->first(fn($c) => $c->epg_id === $channelId);
            }

            if ($dbChannel) {
                $mapping[$channelId] = [
                    'db_channel_id'  => $dbChannel->id,
                    'epg_channel_id' => $channelId,
                    'name'           => $channelData['name'] ?? null,
                ];
            }
        }

        return $mapping;
    }

    private function parseProgrammeElement(\SimpleXMLElement $programme, array $channels): ?array
    {
        try {
            $channelId = (string) $programme['channel'];
            $startTime = $this->parseDate((string) $programme['start']);
            $endTime = $this->parseDate((string) $programme['stop']);

            if (!$startTime || !$endTime) {
                return null;
            }

            $title = '';
            if (isset($programme->title)) {
                $title = (string) $programme->title;
            }

            $description = '';
            if (isset($programme->desc)) {
                $description = (string) $programme->desc;
            }

            $genre = $this->extractGenre($programme);

            $language = null;
            if (isset($programme->language)) {
                $language = (string) $programme->language;
            }

            $rating = null;
            if (isset($programme->rating) && isset($programme->rating->value)) {
                $rating = (string) $programme->rating->value;
            }

            $metadata = $this->extractMetadata($programme);

            return [
                'external_id'     => $this->generateExternalId($channelId, $startTime),
                'epg_channel_id'  => $channelId,
                'title'           => $title,
                'description'     => $description,
                'start_time'      => $startTime,
                'end_time'        => $endTime,
                'genre'           => $genre,
                'language'        => $language,
                'rating'          => $rating,
                'season'          => $metadata['season'] ?? null,
                'episode'         => $metadata['episode'] ?? null,
                'episode_title'   => $metadata['episode_title'] ?? null,
                'icon'            => $this->extractIcon($programme),
                'subtitles'       => $this->extractSubtitles($programme),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to parse programme element', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractGenre(\SimpleXMLElement $programme): ?string
    {
        if (isset($programme->category)) {
            $categories = [];

            foreach ($programme->category as $category) {
                $categories[] = (string) $category;
            }

            return implode(', ', $categories);
        }

        return null;
    }

    private function extractIcon(\SimpleXMLElement $programme): ?string
    {
        if (isset($programme->icon) && isset($programme->icon['src'])) {
            return (string) $programme->icon['src'];
        }

        return null;
    }

    private function extractSubtitles(\SimpleXMLElement $programme): ?array
    {
        if (!isset($programme->subtitles)) {
            return null;
        }

        $subs = [];
        foreach ($programme->subtitles as $sub) {
            $subs[] = [
                'language' => isset($sub['lang']) ? (string) $sub['lang'] : null,
                'type'     => isset($sub['type']) ? (string) $sub['type'] : 'teletext',
            ];
        }

        return $subs ?: null;
    }

    private function extractMetadata(\SimpleXMLElement $programme): array
    {
        $metadata = [];

        if (isset($programme->children()->{'episode-num'})) {
            $epStr = (string) $programme->children()->{'episode-num'};
            // Parse "S01E05" or "1/5" or "Ep. 5" formats
            if (preg_match('/^S(\d+)E(\d+)$/i', $epStr, $m)) {
                $metadata['season']  = $m[1];
                $metadata['episode'] = $m[2];
            } elseif (preg_match('/^(\d+)\/(\d+)$/', $epStr, $m)) {
                $metadata['season']  = $m[1];
                $metadata['episode'] = $m[2];
            } elseif (preg_match('/^(?:Ep(?:isode)?\.?\s*)(\d+)$/i', $epStr, $m)) {
                $metadata['episode'] = $m[1];
            }
        }

        if (isset($programme->credits)) {
            $actors = [];
            if (isset($programme->credits->actor)) {
                foreach ($programme->credits->actor as $actor) {
                    $actors[] = (string) $actor;
                }
            }
            if (!empty($actors)) {
                $metadata['episode_title'] = implode(', ', array_slice($actors, 0, 3));
            }
        }

        return $metadata;
    }

    private function generateExternalId(string $channelId, string $startTime): string
    {
        return md5("{$channelId}:{$startTime}");
    }

    /**
     * Decompress gzipped content if it starts with the gzip magic bytes.
     */
    private function decompressIfGzipped(string $content): string
    {
        if (strlen($content) < 2) {
            return $content;
        }

        // gzip magic number: 0x1f 0x8b
        if (ord($content[0]) === 0x1f && ord($content[1]) === 0x8b) {
            $decompressed = @gzdecode($content);

            if ($decompressed !== false) {
                return $decompressed;
            }
        }

        return $content;
    }
}
