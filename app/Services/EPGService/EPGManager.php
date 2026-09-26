<?php

declare(strict_types=1);

namespace App\Services\EPGService;

use App\Contracts\EPG\EPGManagerInterface;
use App\Models\EPGProgram;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EPGManager implements EPGManagerInterface
{
    private XMLTVParser $parser;
    private EPGCache $cache;

    private const CACHE_TTL = 3600;
    private const BATCH_SIZE = 500;

    public function __construct(XMLTVParser $parser, EPGCache $cache)
    {
        $this->parser = $parser;
        $this->cache = $cache;
    }

    /**
     * Fetch XMLTV from a URL, parse, and store programs.
     * Handles gzipped content and HTTP redirects.
     */
    public function fetchEPG(string $url): array
    {
        Log::info('Fetching EPG data', ['url' => $url]);

        $timeout = (int) config('epg.processing.fetch_timeout', 120);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'IPTV-Middleware/1.0'])
                ->withOptions(['verify' => false])
                ->get($url);

            if ($response->failed()) {
                throw new \RuntimeException("Failed to fetch EPG: HTTP {$response->status()} from {$url}");
            }

            $content = $response->body();

            $result = $this->parser->parse($content);

            return $result;
        } catch (\Exception $e) {
            Log::error('EPG fetch failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get programs for a specific channel, optionally filtered by date.
     */
    public function getProgramsForChannel(int $channelId, ?string $date = null): array
    {
        $cacheKey = "epg:channel:{$channelId}:" . ($date ?? 'all');

        return $this->cache->getPrograms($cacheKey, function () use ($channelId, $date) {
            $query = EPGProgram::where('channel_id', $channelId);

            if ($date) {
                $query->whereDate('start_time', $date);
            }

            return $query->orderBy('start_time')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get the program currently airing on a channel.
     */
    public function getCurrentProgram(int $channelId): ?array
    {
        $now = now();
        $cacheKey = "epg:channel:{$channelId}:current";

        return $this->cache->getPrograms($cacheKey, function () use ($channelId, $now) {
            return EPGProgram::where('channel_id', $channelId)
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->first()
                ?->toArray();
        });
    }

    /**
     * Get programs within a time range.
     */
    public function getProgramsByTimeRange(string $startTime, string $endTime, ?int $channelId = null): array
    {
        $query = EPGProgram::where('start_time', '>=', $startTime)
            ->where('end_time', '<=', $endTime);

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        return $query->orderBy('start_time')
            ->get()
            ->toArray();
    }

    /**
     * Fetch EPG from URL, resolve channels, and store programs in DB.
     */
    public function updateEPGData(string $url): int
    {
        Log::info('Updating EPG data', ['url' => $url]);

        $result = $this->fetchEPG($url);
        $programs = $result['programs'] ?? [];
        $channelMapping = $this->buildChannelMapping();

        if (empty($channelMapping)) {
            Log::warning('No channel mappings found — EPG programs will not be stored', ['url' => $url]);
            return 0;
        }

        $updated = 0;

        foreach (array_chunk($programs, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $program) {
                $epgChannelId = $program['epg_channel_id'] ?? null;

                if (!$epgChannelId || !isset($channelMapping[$epgChannelId])) {
                    continue;
                }

                $dbChannelId = $channelMapping[$epgChannelId]['db_channel_id'];
                $epgSourceId = $channelMapping[$epgChannelId]['epg_source_id'];

                EPGProgram::updateOrCreate(
                    [
                        'program_id'     => $program['external_id'],
                        'channel_id'     => $dbChannelId,
                        'epg_source_id'  => $epgSourceId,
                    ],
                    [
                        'title'         => $program['title'],
                        'description'   => $program['description'] ?? null,
                        'start_time'    => $program['start_time'],
                        'end_time'      => $program['end_time'],
                        'category'      => $program['genre'] ?? null,
                        'language'      => $program['language'] ?? null,
                        'rating'        => $program['rating'] ?? null,
                        'season'        => $program['season'] ?? null,
                        'episode'       => $program['episode'] ?? null,
                        'episode_title' => $program['episode_title'] ?? null,
                        'subtitles'     => $program['subtitles'] ?? null,
                    ]
                );

                $updated++;
            }
        }

        $this->clearCache();

        Log::info('EPG data updated', ['programs_updated' => $updated]);

        return $updated;
    }

    /**
     * Delete programs that ended before the cutoff.
     */
    public function deleteExpiredPrograms(int $daysToKeep = 7): int
    {
        $cutoff = now()->subDays($daysToKeep);

        $deleted = EPGProgram::where('end_time', '<', $cutoff)->delete();

        Log::info('Expired EPG programs deleted', [
            'deleted_count' => $deleted,
            'cutoff_date'   => $cutoff->toDateString(),
        ]);

        return $deleted;
    }

    public function getEPGStats(): array
    {
        return Cache::remember('epg:stats', self::CACHE_TTL, function () {
            return [
                'total_programs'         => EPGProgram::count(),
                'channels_with_programs' => EPGProgram::distinct('channel_id')->count(),
                'latest_update'          => EPGProgram::latest('updated_at')->value('updated_at'),
                'date_range'             => [
                    'start' => EPGProgram::min('start_time'),
                    'end'   => EPGProgram::max('end_time'),
                ],
            ];
        });
    }

    public function searchPrograms(string $query): array
    {
        return Cache::remember(
            "epg:search:" . md5($query),
            self::CACHE_TTL,
            function () use ($query) {
                return EPGProgram::where('title', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%")
                    ->orderBy('start_time', 'desc')
                    ->limit(100)
                    ->get()
                    ->toArray();
            }
        );
    }

    public function getChannelEPG(int $channelId, int $limit = 50): array
    {
        return Cache::remember(
            "epg:channel:{$channelId}:list:{$limit}",
            self::CACHE_TTL,
            function () use ($channelId, $limit) {
                return EPGProgram::where('channel_id', $channelId)
                    ->where('end_time', '>=', now())
                    ->orderBy('start_time')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            }
        );
    }

    /**
     * Import from raw XMLTV content (may be gzipped).
     */
    public function importFromXMLTV(string $content): int
    {
        $result = $this->parser->parse($content);
        $programs = $result['programs'] ?? [];
        $channelMapping = $this->buildChannelMapping();

        $imported = 0;

        foreach (array_chunk($programs, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $program) {
                $epgChannelId = $program['epg_channel_id'] ?? null;

                if (!$epgChannelId || !isset($channelMapping[$epgChannelId])) {
                    continue;
                }

                $dbChannelId = $channelMapping[$epgChannelId]['db_channel_id'];
                $epgSourceId = $channelMapping[$epgChannelId]['epg_source_id'];

                EPGProgram::updateOrCreate(
                    [
                        'program_id'    => $program['external_id'],
                        'channel_id'    => $dbChannelId,
                        'epg_source_id' => $epgSourceId,
                    ],
                    [
                        'title'         => $program['title'],
                        'description'   => $program['description'] ?? null,
                        'start_time'    => $program['start_time'],
                        'end_time'      => $program['end_time'],
                        'category'      => $program['genre'] ?? null,
                        'language'      => $program['language'] ?? null,
                        'rating'        => $program['rating'] ?? null,
                        'season'        => $program['season'] ?? null,
                        'episode'       => $program['episode'] ?? null,
                        'episode_title' => $program['episode_title'] ?? null,
                        'subtitles'     => $program['subtitles'] ?? null,
                    ]
                );

                $imported++;
            }
        }

        $this->clearCache();

        return $imported;
    }

    public function clearCache(): void
    {
        $this->cache->clear();
        Cache::forget('epg:stats');
    }

    public function getNextProgram(int $channelId): ?array
    {
        $now = now();
        $cacheKey = "epg:channel:{$channelId}:next";

        return $this->cache->getPrograms($cacheKey, function () use ($channelId, $now) {
            return EPGProgram::where('channel_id', $channelId)
                ->where('start_time', '>', $now)
                ->orderBy('start_time')
                ->first()
                ?->toArray();
        });
    }

    public function getPrimeTimePrograms(int $channelId, ?string $date = null): array
    {
        $date = $date ?? now()->toDateString();
        $startTime = "{$date} 20:00:00";
        $endTime = "{$date} 23:00:00";

        $cacheKey = "epg:channel:{$channelId}:primetime:{$date}";

        return $this->cache->getPrograms($cacheKey, function () use ($channelId, $startTime, $endTime) {
            return EPGProgram::where('channel_id', $channelId)
                ->where('start_time', '>=', $startTime)
                ->where('start_time', '<', $endTime)
                ->orderBy('start_time')
                ->get()
                ->toArray();
        });
    }

    /**
     * Build a mapping of epg_channel_id => ['db_channel_id' => ..., 'epg_source_id' => ...].
     *
     * Matches channels.epg_channel_id (primary) or channels.epg_id (fallback).
     */
    private function buildChannelMapping(): array
    {
        $channels = Channel::where('is_active', true)
            ->where(fn($q) => $q->whereNotNull('epg_channel_id')->orWhereNotNull('epg_id'))
            ->get(['id', 'epg_channel_id', 'epg_id']);

        $mapping = [];

        foreach ($channels as $ch) {
            if ($ch->epg_channel_id) {
                $mapping[$ch->epg_channel_id] = [
                    'db_channel_id' => $ch->id,
                    'epg_source_id' => $ch->epg_source_id,
                ];
            }
            if ($ch->epg_id && $ch->epg_id !== $ch->epg_channel_id) {
                $mapping[$ch->epg_id] = [
                    'db_channel_id' => $ch->id,
                    'epg_source_id' => $ch->epg_source_id,
                ];
            }
        }

        return $mapping;
    }
}
