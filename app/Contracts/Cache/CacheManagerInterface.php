<?php

declare(strict_types=1);

namespace App\Contracts\Cache;

interface CacheManagerInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function forget(string $key): bool;

    public function has(string $key): bool;

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed;

    public function flush(): bool;

    public function increment(string $key, int $value = 1): int|bool;

    public function decrement(string $key, int $value = 1): int|bool;

    public function tags(array $tags): static;

    public function tag(string $tag): static;

    public function flushTags(array $tags): bool;

    public function getMultiple(array $keys, mixed $default = null): array;

    public function setMultiple(array $values, ?int $ttl = null): bool;

    public function forgetMultiple(array $keys): bool;

    public function add(string $key, mixed $value, ?int $ttl = null): bool;

    public function forever(string $key, mixed $value): bool;

    public function pull(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, ?int $ttl = null): bool;

    public function section(string $name): static;

    public function getStats(): array;

    public function warmCache(string $type, callable $callback, ?int $ttl = null): void;

    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed;

    public function invalidatePattern(string $pattern): int;

    public function getCacheKeys(string $pattern = '*'): array;

    public function getCacheSize(): int;

    public function clearExpired(): int;
}
