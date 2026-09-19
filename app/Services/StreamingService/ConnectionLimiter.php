<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Enforces per-user concurrent stream limits using Redis atomic counters.
 *
 * Each active stream holds a slot for SLOT_TTL seconds. The player must
 * re-request the stream (HLS playlist refresh every ~6s) to keep the slot
 * alive. A slot that isn't refreshed expires automatically, so a crashed
 * player never permanently blocks a connection slot.
 */
class ConnectionLimiter
{
    // Slot lifetime in seconds. Must be > HLS playlist refresh interval (6s).
    // 30s gives 4 missed refreshes before the slot expires.
    private const SLOT_TTL = 30;

    /**
     * Try to acquire a stream slot for $user.
     * Returns true if allowed, false if the connection limit is reached.
     */
    public function acquire(User $user, string $streamKey): bool
    {
        $max = (int) ($user->max_connections ?? 1);
        if ($max <= 0) {
            return false;
        }

        $slotKey = $this->slotKey($user->id, $streamKey);

        // Refresh existing slot — same user re-requesting the same stream.
        if (Cache::has($slotKey)) {
            Cache::put($slotKey, true, self::SLOT_TTL);
            return true;
        }

        // Count active slots for this user.
        $active = $this->activeCount($user->id);
        if ($active >= $max) {
            return false;
        }

        Cache::put($slotKey, true, self::SLOT_TTL);
        return true;
    }

    /**
     * Explicitly release a slot (called on stream end / logout).
     */
    public function release(int $userId, string $streamKey): void
    {
        Cache::forget($this->slotKey($userId, $streamKey));
    }

    public function activeCount(int $userId): int
    {
        // Redis KEYS scan — acceptable for small slot counts per user.
        // Falls back to 0 on non-Redis drivers.
        try {
            $redis  = Cache::getRedis();
            $prefix = Cache::getPrefix();
            $keys   = $redis->keys("{$prefix}stream:slot:{$userId}:*");
            return count($keys);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function slotKey(int $userId, string $streamKey): string
    {
        return "stream:slot:{$userId}:{$streamKey}";
    }
}
