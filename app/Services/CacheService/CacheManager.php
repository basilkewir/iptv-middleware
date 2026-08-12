<?php

declare(strict_types=1);

namespace App\Services\CacheService;

use App\Contracts\Cache\CacheManagerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheManager implements CacheManagerInterface
{
    private RedisCache $redisCache;
    private string $defaultDriver;
    private int $defaultTtl;

    public function __construct(RedisCache $redisCache)
    {
        $this->redisCache = $redisCache;
        $this->defaultDriver = config('cache.default', 'redis');
        $this->defaultTtl = config('cache.ttl', 3600);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getDriver()->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;

        return $this->getDriver()->set($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->getDriver()->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->getDriver()->has($key);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;

        return $this->getDriver()->remember($key, $callback, $ttl);
    }

    public function flush(): bool
    {
        return $this->getDriver()->flush();
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->getDriver()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->getDriver()->decrement($key, $value);
    }

    public function tags(array $tags): static
    {
        return $this->getDriver()->tags($tags);
    }

    public function tag(string $tag): static
    {
        return $this->getDriver()->tag($tag);
    }

    public function flushTags(array $tags): bool
    {
        return $this->getDriver()->flushTags($tags);
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        return $this->getDriver()->getMultiple($keys, $default);
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;

        return $this->getDriver()->setMultiple($values, $ttl);
    }

    public function forgetMultiple(array $keys): bool
    {
        return $this->getDriver()->forgetMultiple($keys);
    }

    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;

        return $this->getDriver()->add($key, $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->getDriver()->forever($key, $value);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->getDriver()->pull($key, $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;

        return $this->getDriver()->put($key, $value, $ttl);
    }

    public function section(string $name): static
    {
        return $this->getDriver()->section($name);
    }

    public function getStats(): array
    {
        return $this->getDriver()->getStats();
    }

    public function warmCache(string $type, callable $callback, ?int $ttl = null): void
    {
        $ttl = $ttl ?? $this->defaultTtl;

        $data = $callback();

        $this->set("cache:warm:{$type}", $data, $ttl);

        Log::info('Cache warmed', ['type' => $type, 'items' => count($data)]);
    }

    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;

        return $this->getDriver()->remember($key, $callback, $ttl);
    }

    public function invalidatePattern(string $pattern): int
    {
        return $this->getDriver()->invalidatePattern($pattern);
    }

    public function getCacheKeys(string $pattern = '*'): array
    {
        return $this->getDriver()->getCacheKeys($pattern);
    }

    public function getCacheSize(): int
    {
        return $this->getDriver()->getCacheSize();
    }

    public function clearExpired(): int
    {
        return $this->getDriver()->clearExpired();
    }

    private function getDriver(): RedisCache
    {
        return match ($this->defaultDriver) {
            'redis' => $this->redisCache,
            default => $this->redisCache,
        };
    }
}
