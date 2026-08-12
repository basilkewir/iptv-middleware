<?php

declare(strict_types=1);

namespace App\Contracts\Streaming;

use App\Models\Stream;
use App\Models\Channel;

interface StreamManagerInterface
{
    public function startStream(Channel $channel): Stream;

    public function stopStream(Stream $stream): void;

    public function getActiveStreamForChannel(int $channelId): ?Stream;

    public function getStreamConnections(Stream $stream): int;

    public function addConnection(Stream $stream): void;

    public function removeConnection(Stream $stream): void;

    public function getStreamUrl(Stream $stream): string;

    public function validateStreamKey(string $streamKey): bool;

    public function getStreamStats(Stream $stream): array;
}
