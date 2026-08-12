<?php

declare(strict_types=1);

namespace App\Contracts\EPG;

interface EPGCacheInterface
{
    public function getPrograms(string $key, callable $callback, ?int $ttl = null): array;

    public function storePrograms(array $programs): void;

    public function getChannelPrograms(int $channelId, callable $callback, ?int $ttl = null): array;

    public function clearChannelCache(int $channelId): void;

    public function clear(): void;

    public function getStats(): array;

    public function searchCache(string $query, callable $callback, ?int $ttl = null): array;

    public function clearSearchCache(): void;

    public function warmCache(): void;

    public function getCacheStats(): array;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function get(string $key, mixed $default = null): mixed;

    public function forget(string $key): bool;

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed;

    public function increment(string $key, int $value = 1): int|bool;

    public function decrement(string $key, int $value = 1): int|bool;

    public function has(string $key): bool;
}
