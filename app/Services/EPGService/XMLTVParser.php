<?php

declare(strict_types=1);

namespace App\Services\EPGService;

use App\Contracts\EPG\XMLTVParserInterface;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class XMLTVParser implements XMLTVParserInterface
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s';

    public function parse(string $content): array
    {
        try {
            libxml_use_internal_errors(true);

            $xml = new SimpleXMLElement($content);

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

            return $programs;
        } catch (\Exception $e) {
            Log::error('XMLTV parsing failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function parseChannels(string $content): array
    {
        libxml_use_internal_errors(true);

        $xml = new SimpleXMLElement($content);

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
                    'id' => $channelId,
                    'name' => $name,
                    'icon' => $icon,
                    'language' => $language,
                ];
            }
        }

        return $channels;
    }

    public function parseProgramme(string $content, array $channels = []): array
    {
        libxml_use_internal_errors(true);

        $xml = new SimpleXMLElement($content);

        $programs = [];

        if (isset($xml->programme)) {
            foreach ($xml->programme as $programme) {
                $program = $this->parseProgrammeElement($programme, $channels);

                if ($program) {
                    $programs[] = $program;
                }
            }
        }

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
        libxml_use_internal_errors(true);

        $xml = new SimpleXMLElement($content);

        if ($xml === false) {
            return false;
        }

        $errors = libxml_get_errors();

        return empty($errors);
    }

    public function getChannelIdMapping(string $content): array
    {
        $channels = $this->parseChannels($content);
        $mapping = [];

        foreach ($channels as $channelId => $channelData) {
            $dbChannel = Channel::where('epg_id', $channelId)->first();

            if ($dbChannel) {
                $mapping[$channelId] = $dbChannel->id;
            }
        }

        return $mapping;
    }

    private function parseProgrammeElement(SimpleXMLElement $programme, array $channels): ?array
    {
        try {
            $channelId = (string) $programme['channel'];
            $startTime = $this->parseDate((string) $programme['start']);
            $endTime = $this->parseDate((string) $programme['stop']);

            if (!$startTime || !$endTime) {
                return null;
            }

            $externalId = $this->generateExternalId($channelId, $startTime);

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

            $thumbnail = null;
            if (isset($programme->icon)) {
                $thumbnail = (string) $programme->icon['src'];
            }

            $metadata = $this->extractMetadata($programme);

            $dbChannelId = $channels[$channelId]['db_id'] ?? null;

            return [
                'external_id' => $externalId,
                'channel_id' => $dbChannelId,
                'epg_channel_id' => $channelId,
                'title' => $title,
                'description' => $description,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'genre' => $genre,
                'language' => $language,
                'thumbnail' => $thumbnail,
                'metadata' => $metadata,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to parse programme element', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractGenre(SimpleXMLElement $programme): ?string
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

    private function extractMetadata(SimpleXMLElement $programme): ?array
    {
        $metadata = [];

        if (isset($programme->children()->{'episode-num'})) {
            $metadata['episode'] = (string) $programme->children()->{'episode-num'};
        }

        if (isset($programme->season)) {
            $metadata['season'] = (string) $programme->season;
        }

        if (isset($programme->episode)) {
            $metadata['episode_number'] = (string) $programme->episode;
        }

        if (isset($programme->credits)) {
            $credits = [];

            if (isset($programme->credits->director)) {
                foreach ($programme->credits->director as $director) {
                    $credits['directors'][] = (string) $director;
                }
            }

            if (isset($programme->credits->actor)) {
                foreach ($programme->credits->actor as $actor) {
                    $credits['actors'][] = (string) $actor;
                }
            }

            if (!empty($credits)) {
                $metadata['credits'] = $credits;
            }
        }

        if (isset($programme->rating)) {
            $metadata['rating'] = (string) $programme->rating->value;
        }

        if (isset($programme->aspect)) {
            $metadata['aspect'] = (string) $programme->aspect;
        }

        return !empty($metadata) ? $metadata : null;
    }

    private function generateExternalId(string $channelId, string $startTime): string
    {
        return md5("{$channelId}:{$startTime}");
    }
}
