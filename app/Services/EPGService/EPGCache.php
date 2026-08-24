<?php

declare(strict_types=1);

namespace App\Services\EPGService;

use App\Contracts\EPG\EPGCacheInterface;
use App\Models\EPGData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class EPGCache implements EPGCacheInterface
{
    private string $prefix;
    private int $defaultTtl;

    private const TAG_CHANNEL = 'epg:channel';
    private const TAG_PROGRAM = 'epg:program';
    private const TAG_SEARCH = 'epg:search';
    private const TAG_STATS = 'epg:stats';

    public function __construct()
    {
        $this->prefix = config('cache.prefix', 'iptv') . ':epg:';
        $this->defaultTtl = (int) config('epg.cache_ttl', 14400);
    }

    public function getPrograms(string $key, callable $callback, ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $fullKey = $this->prefix . $key;

        return Cache::tags([self::TAG_PROGRAM])->remember($fullKey, $ttl, $callback);
    }

    public function storePrograms(array $programs): void
    {
        foreach ($programs as $program) {
            $channelId = $program['channel_id'] ?? null;

            if ($channelId) {
                $channelKey = self::TAG_CHANNEL . ":{$channelId}";
                Cache::tags([self::TAG_CHANNEL])->put(
                    $channelKey,
                    $programs,
                    $this->defaultTtl
                );
            }
        }

        Log::info('Programs stored in cache', ['count' => count($programs)]);
    }

    public function getChannelPrograms(int $channelId, callable $callback, ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = self::TAG_CHANNEL . ":{$channelId}";

        return Cache::tags([self::TAG_CHANNEL])->remember($key, $ttl, $callback);
    }

    public function clearChannelCache(int $channelId): void
    {
        $key = self::TAG_CHANNEL . ":{$channelId}";
        Cache::tags([self::TAG_CHANNEL])->forget($key);

        Log::info('Channel cache cleared', ['channel_id' => $channelId]);
    }

    public function clear(): void
    {
        Cache::tags([self::TAG_PROGRAM, self::TAG_CHANNEL, self::TAG_SEARCH, self::TAG_STATS])->flush();

        Log::info('EPG cache cleared');
    }

    public function getStats(): array
    {
        return Cache::tags([self::TAG_STATS])->remember(
            'epg:stats',
            $this->defaultTtl,
            function () {
                return [
                    'total_programs' => EPGData::count(),
                    'channels_with_programs' => EPGData::distinct('channel_id')->count(),
                    'cache_driver' => config('cache.default'),
                    'cache_prefix' => $this->prefix,
                ];
            }
        );
    }

    public function searchCache(string $query, callable $callback, ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = self::TAG_SEARCH . ':' . md5($query);

        return Cache::tags([self::TAG_SEARCH])->remember($key, $ttl, $callback);
    }

    public function clearSearchCache(): void
    {
        Cache::tags([self::TAG_SEARCH])->flush();
    }

    public function warmCache(): void
    {
        Log::info('Warming EPG cache');

        $channels = EPGData::select('channel_id')
            ->distinct()
            ->get();

        foreach ($channels as $channel) {
            $programs = EPGData::where('channel_id', $channel->channel_id)
                ->where('end_time', '>=', now())
                ->orderBy('start_time')
                ->get()
                ->toArray();

            $key = self::TAG_CHANNEL . ":{$channel->channel_id}";
            Cache::tags([self::TAG_CHANNEL])->put($key, $programs, $this->defaultTtl);
        }

        Log::info('EPG cache warmed', ['channels' => $channels->count()]);
    }

    public function getCacheStats(): array
    {
        try {
            $redis = Redis::connection();

            $info = $redis->info();
            $memory = $redis->info('memory');

            return [
                'connected' => true,
                'used_memory' => $memory['used_memory_human'] ?? 'N/A',
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $fullKey = $this->prefix . $key;

        return Cache::put($fullKey, $value, $ttl);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->prefix . $key;

        return Cache::get($fullKey, $default);
    }

    public function forget(string $key): bool
    {
        $fullKey = $this->prefix . $key;

        return Cache::forget($fullKey);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $fullKey = $this->prefix . $key;

        return Cache::remember($fullKey, $ttl, $callback);
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $fullKey = $this->prefix . $key;

        return Cache::increment($fullKey, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        $fullKey = $this->prefix . $key;

        return Cache::decrement($fullKey, $value);
    }

    public function has(string $key): bool
    {
        $fullKey = $this->prefix . $key;

        return Cache::has($fullKey);
    }

    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return 0.0;
        }

        return round(($hits / $total) * 100, 2);
    }
}
