<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Enforces per-user concurrent stream limits using Redis atomic counters.
 *
 * Uses Redis INCR/DECR for non-blocking, atomic operations that never
 * block the request pipeline. Each active stream holds a slot for SLOT_TTL
 * seconds. The player must re-request the stream (HLS playlist refresh
 * every ~2s with low-latency config) to keep the slot alive. A slot that
 * isn't refreshed expires automatically, so a crashed player never
 * permanently blocks a connection slot.
 */
class ConnectionLimiter
{
    // Slot lifetime in seconds. Must be > HLS playlist refresh interval (2s).
    // 10s gives 4 missed refreshes before the slot expires.
    private const SLOT_TTL = 10;

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
        $counterKey = $this->counterKey($user->id);

        // Refresh existing slot — same user re-requesting the same stream.
        if (Cache::has($slotKey)) {
            Cache::put($slotKey, true, self::SLOT_TTL);
            return true;
        }

        // Atomically increment the counter and check limit.
        $current = (int) Redis::incr($counterKey);
        Redis::expire($counterKey, self::SLOT_TTL);

        if ($current > $max) {
            // Over limit — revert immediately.
            Redis::decr($counterKey);
            return false;
        }

        // Acquired — set the per-stream slot key.
        Cache::put($slotKey, true, self::SLOT_TTL);
        return true;
    }

    /**
     * Explicitly release a slot (called on stream end / logout).
     */
    public function release(int $userId, string $streamKey): void
    {
        $slotKey = $this->slotKey($userId, $streamKey);
        if (Cache::has($slotKey)) {
            Cache::forget($slotKey);
            $counterKey = $this->counterKey($userId);
            $val = (int) Redis::get($counterKey);
            if ($val > 0) {
                Redis::decr($counterKey);
            }
        }
    }

    public function activeCount(int $userId): int
    {
        try {
            $counterKey = $this->counterKey($userId);
            return max(0, (int) Redis::get($counterKey));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function slotKey(int $userId, string $streamKey): string
    {
        return "stream:slot:{$userId}:{$streamKey}";
    }

    private function counterKey(int $userId): string
    {
        return "stream:counter:{$userId}";
    }
}
