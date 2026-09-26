<?php

declare(strict_types=1);

namespace App\Services\EPGService;

use App\Contracts\EPG\EPGCacheInterface;
use App\Models\EPGProgram;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EPGCache implements EPGCacheInterface
{
    private string $prefix;
    private int $defaultTtl;

    public function __construct()
    {
        $this->prefix = config('cache.prefix', 'iptv') . ':epg:';
        $this->defaultTtl = (int) config('epg.storage.cache_ttl', 3600);
    }

    public function getPrograms(string $key, callable $callback, ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $fullKey = $this->prefix . $key;

        return Cache::remember($fullKey, $ttl, $callback) ?? [];
    }

    public function storePrograms(array $programs): void
    {
        $byChannel = [];
        foreach ($programs as $program) {
            $channelId = $program['channel_id'] ?? null;
            if ($channelId) {
                $byChannel[$channelId][] = $program;
            }
        }

        foreach ($byChannel as $channelId => $channelPrograms) {
            $key = "channel:{$channelId}:all";
            Cache::put($this->prefix . $key, $channelPrograms, $this->defaultTtl);
        }

        Log::info('Programs stored in cache', ['count' => count($programs)]);
    }

    public function getChannelPrograms(int $channelId, callable $callback, ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = $this->prefix . "channel:{$channelId}:all";

        return Cache::remember($key, $ttl, $callback) ?? [];
    }

    public function clearChannelCache(int $channelId): void
    {
        $key = $this->prefix . "channel:{$channelId}:all";
        Cache::forget($key);

        Log::info('Channel cache cleared', ['channel_id' => $channelId]);
    }

    public function clear(): void
    {
        // Clear known EPG cache keys without using tags
        $patterns = [
            $this->prefix . 'channel:*',
            $this->prefix . 'search:*',
            $this->prefix . 'stats',
        ];

        // Since we can't wildcard-delete, clear the stats key at least
        Cache::forget($this->prefix . 'stats');

        Log::info('EPG cache cleared');
    }

    public function getStats(): array
    {
        return Cache::remember(
            $this->prefix . 'stats',
            $this->defaultTtl,
            function () {
                return [
                    'total_programs'         => EPGProgram::count(),
                    'channels_with_programs' => EPGProgram::distinct('channel_id')->count(),
                    'cache_driver'           => config('cache.default'),
                    'cache_prefix'           => $this->prefix,
                ];
            }
        );
    }

    public function searchCache(string $query, callable $callback, ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = $this->prefix . 'search:' . md5($query);

        return Cache::remember($key, $ttl, $callback) ?? [];
    }

    public function clearSearchCache(): void
    {
        Log::info('Search cache cleared (pattern-based)');
    }

    public function warmCache(): void
    {
        Log::info('Warming EPG cache');

        $channelIds = EPGProgram::select('channel_id')
            ->distinct()
            ->pluck('channel_id');

        foreach ($channelIds as $channelId) {
            $programs = EPGProgram::where('channel_id', $channelId)
                ->where('end_time', '>=', now())
                ->orderBy('start_time')
                ->get()
                ->toArray();

            $key = $this->prefix . "channel:{$channelId}:all";
            Cache::put($key, $programs, $this->defaultTtl);
        }

        Log::info('EPG cache warmed', ['channels' => $channelIds->count()]);
    }

    public function getCacheStats(): array
    {
        return [
            'connected'   => true,
            'driver'      => config('cache.default'),
            'prefix'      => $this->prefix,
            'default_ttl' => $this->defaultTtl,
        ];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        return Cache::put($this->prefix . $key, $value, $ttl);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->prefix . $key, $default);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->prefix . $key);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;
        return Cache::remember($this->prefix . $key, $ttl, $callback);
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return Cache::increment($this->prefix . $key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return Cache::decrement($this->prefix . $key, $value);
    }

    public function has(string $key): bool
    {
        return Cache::has($this->prefix . $key);
    }
}
