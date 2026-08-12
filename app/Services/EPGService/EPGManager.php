<?php

declare(strict_types=1);

namespace App\Services\EPGService;

use App\Contracts\EPG\EPGManagerInterface;
use App\Models\EPGData;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EPGManager implements EPGManagerInterface
{
    private XMLTVParser $parser;
    private EPGCache $cache;

    private const CACHE_TTL = 14400;
    private const BATCH_SIZE = 500;

    public function __construct(XMLTVParser $parser, EPGCache $cache)
    {
        $this->parser = $parser;
        $this->cache = $cache;
    }

    public function fetchEPG(string $url): array
    {
        Log::info('Fetching EPG data', ['url' => $url]);

        try {
            $response = Http::timeout(120)
                ->withHeaders(['User-Agent' => 'IPTV-Middleware/1.0'])
                ->get($url);

            if ($response->failed()) {
                throw new \RuntimeException("Failed to fetch EPG: {$response->status()}");
            }

            $content = $response->body();

            $programs = $this->parser->parse($content);

            $this->cache->storePrograms($programs);

            Log::info('EPG data fetched and cached', [
                'url' => $url,
                'programs_count' => count($programs),
            ]);

            return $programs;
        } catch (\Exception $e) {
            Log::error('EPG fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getProgramsForChannel(int $channelId, ?string $date = null): array
    {
        $cacheKey = "epg:channel:{$channelId}:" . ($date ?? 'all');

        return $this->cache->getPrograms($cacheKey, function () use ($channelId, $date) {
            $query = EPGData::where('channel_id', $channelId);

            if ($date) {
                $query->whereDate('start_time', $date);
            }

            return $query->orderBy('start_time')
                ->get()
                ->toArray();
        });
    }

    public function getCurrentProgram(int $channelId): ?array
    {
        $now = now();

        $cacheKey = "epg:channel:{$channelId}:current";

        return $this->cache->getPrograms($cacheKey, function () use ($channelId, $now) {
            return EPGData::where('channel_id', $channelId)
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->first()
                ?->toArray();
        });
    }

    public function getProgramsByTimeRange(string $startTime, string $endTime, ?int $channelId = null): array
    {
        $query = EPGData::where('start_time', '>=', $startTime)
            ->where('end_time', '<=', $endTime);

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        return $query->orderBy('start_time')
            ->get()
            ->toArray();
    }

    public function updateEPGData(string $url): int
    {
        Log::info('Updating EPG data', ['url' => $url]);

        $programs = $this->fetchEPG($url);

        $updated = 0;

        foreach (array_chunk($programs, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $program) {
                EPGData::updateOrCreate(
                    [
                        'external_id' => $program['external_id'],
                        'channel_id' => $program['channel_id'],
                    ],
                    [
                        'title' => $program['title'],
                        'description' => $program['description'] ?? null,
                        'start_time' => $program['start_time'],
                        'end_time' => $program['end_time'],
                        'genre' => $program['genre'] ?? null,
                        'language' => $program['language'] ?? null,
                        'thumbnail' => $program['thumbnail'] ?? null,
                        'metadata' => $program['metadata'] ?? null,
                    ]
                );

                $updated++;
            }
        }

        $this->clearCache();

        Log::info('EPG data updated', ['programs_updated' => $updated]);

        return $updated;
    }

    public function deleteExpiredPrograms(int $daysToKeep = 7): int
    {
        $cutoff = now()->subDays($daysToKeep);

        $deleted = EPGData::where('end_time', '<', $cutoff)->delete();

        Log::info('Expired EPG programs deleted', [
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoff->toDateString(),
        ]);

        return $deleted;
    }

    public function getEPGStats(): array
    {
        return Cache::remember('epg:stats', self::CACHE_TTL, function () {
            return [
                'total_programs' => EPGData::count(),
                'channels_with_programs' => EPGData::distinct('channel_id')->count(),
                'latest_update' => EPGData::latest('updated_at')->value('updated_at'),
                'date_range' => [
                    'start' => EPGData::min('start_time'),
                    'end' => EPGData::max('end_time'),
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
                return EPGData::where('title', 'LIKE', "%{$query}%")
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
                return EPGData::where('channel_id', $channelId)
                    ->where('end_time', '>=', now())
                    ->orderBy('start_time')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            }
        );
    }

    public function importFromXMLTV(string $content): int
    {
        $programs = $this->parser->parse($content);

        $imported = 0;

        foreach (array_chunk($programs, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $program) {
                EPGData::updateOrCreate(
                    [
                        'external_id' => $program['external_id'],
                        'channel_id' => $program['channel_id'],
                    ],
                    [
                        'title' => $program['title'],
                        'description' => $program['description'] ?? null,
                        'start_time' => $program['start_time'],
                        'end_time' => $program['end_time'],
                        'genre' => $program['genre'] ?? null,
                        'language' => $program['language'] ?? null,
                        'thumbnail' => $program['thumbnail'] ?? null,
                        'metadata' => $program['metadata'] ?? null,
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
            return EPGData::where('channel_id', $channelId)
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
            return EPGData::where('channel_id', $channelId)
                ->where('start_time', '>=', $startTime)
                ->where('start_time', '<', $endTime)
                ->orderBy('start_time')
                ->get()
                ->toArray();
        });
    }
}
