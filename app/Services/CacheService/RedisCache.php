<?php

declare(strict_types=1);

namespace App\Services\CacheService;

use App\Contracts\Cache\CacheDriverInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisCache implements CacheDriverInterface
{
    private string $prefix;
    private int $defaultTtl;
    private ?string $currentTag = null;
    private array $currentTags = [];

    public function __construct()
    {
        $this->prefix = config('cache.prefix', 'iptv') . ':';
        $this->defaultTtl = config('cache.ttl', 3600);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->getFullKey($key);

        $value = Cache::get($fullKey);

        return $value !== null ? $value : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $fullKey = $this->getFullKey($key);
        $ttl = $ttl ?? $this->defaultTtl;

        return Cache::put($fullKey, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        $fullKey = $this->getFullKey($key);

        return Cache::forget($fullKey);
    }

    public function has(string $key): bool
    {
        $fullKey = $this->getFullKey($key);

        return Cache::has($fullKey);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $fullKey = $this->getFullKey($key);
        $ttl = $ttl ?? $this->defaultTtl;

        return Cache::remember($fullKey, $ttl, $callback);
    }

    public function flush(): bool
    {
        return Cache::flush();
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $fullKey = $this->getFullKey($key);

        return Cache::increment($fullKey, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        $fullKey = $this->getFullKey($key);

        return Cache::decrement($fullKey, $value);
    }

    public function tags(array $tags): static
    {
        $clone = clone $this;
        $clone->currentTags = $tags;

        return $clone;
    }

    public function tag(string $tag): static
    {
        $clone = clone $this;
        $clone->currentTag = $tag;
        $clone->currentTags[] = $tag;

        return $clone;
    }

    public function flushTags(array $tags): bool
    {
        foreach ($tags as $tag) {
            $taggedKeys = $this->getTaggedKeys($tag);

            foreach ($taggedKeys as $key) {
                Cache::forget($key);
            }

            Cache::forget("tag:{$tag}:keys");
        }

        return true;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function forgetMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->forget($key)) {
                $success = false;
            }
        }

        return $success;
    }

    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        $fullKey = $this->getFullKey($key);

        return Cache::forever($fullKey, $value);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function section(string $name): static
    {
        $clone = clone $this;
        $clone->prefix = $this->prefix . $name . ':';

        return $clone;
    }

    public function getStats(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();
            $memory = $redis->info('memory');

            return [
                'connected' => true,
                'used_memory' => $memory['used_memory_human'] ?? 'N/A',
                'used_memory_peak' => $memory['used_memory_peak_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_connections_received' => $info['total_connections_received'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
                'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function invalidatePattern(string $pattern): int
    {
        $redis = Redis::connection();
        $fullPattern = $this->getFullKey($pattern);
        $keys = $redis->keys($fullPattern);

        $count = 0;

        foreach ($keys as $key) {
            $shortKey = str_replace($this->prefix, '', $key);
            $this->forget($shortKey);
            $count++;
        }

        return $count;
    }

    public function getCacheKeys(string $pattern = '*'): array
    {
        $redis = Redis::connection();
        $fullPattern = $this->getFullKey($pattern);

        return $redis->keys($fullPattern);
    }

    public function getCacheSize(): int
    {
        $redis = Redis::connection();

        return $redis->dbsize();
    }

    public function clearExpired(): int
    {
        return 0;
    }

    public function atomicIncrement(string $key, int $value = 1): int
    {
        $fullKey = $this->getFullKey($key);

        return Redis::connection()->incrby($fullKey, $value);
    }

    public function atomicDecrement(string $key, int $value = 1): int
    {
        $fullKey = $this->getFullKey($key);

        return Redis::connection()->decrby($fullKey, $value);
    }

    public function setEx(string $key, mixed $value, int $ttl): bool
    {
        $fullKey = $this->getFullKey($key);

        return Redis::connection()->setex($fullKey, $ttl, serialize($value));
    }

    public function getSet(string $key, mixed $value): mixed
    {
        $fullKey = $this->getFullKey($key);

        $oldValue = $this->get($key);

        $this->set($key, $value);

        return $oldValue;
    }

    public function exists(string $key): bool
    {
        $fullKey = $this->getFullKey($key);

        return (bool) Redis::connection()->exists($fullKey);
    }

    public function ttl(string $key): int
    {
        $fullKey = $this->getFullKey($key);

        return Redis::connection()->ttl($fullKey);
    }

    public function expire(string $key, int $ttl): bool
    {
        $fullKey = $this->getFullKey($key);

        return (bool) Redis::connection()->expire($fullKey, $ttl);
    }

    public function persist(string $key): bool
    {
        $fullKey = $this->getFullKey($key);

        return (bool) Redis::connection()->persist($fullKey);
    }

    private function getFullKey(string $key): string
    {
        if (!empty($this->currentTags)) {
            $tagPrefix = implode(':', $this->currentTags) . ':';
            return $this->prefix . $tagPrefix . $key;
        }

        return $this->prefix . $key;
    }

    private function getTaggedKeys(string $tag): array
    {
        $tagKey = "tag:{$tag}:keys";

        return Cache::get($tagKey, []);
    }

    private function addToTag(string $tag, string $key): void
    {
        $tagKey = "tag:{$tag}:keys";
        $keys = Cache::get($tagKey, []);

        if (!in_array($key, $keys)) {
            $keys[] = $key;
            Cache::put($tagKey, $keys, 86400);
        }
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
