<?php

declare(strict_types=1);

namespace App\Contracts\Streaming;

use App\Models\Stream;
use App\Models\Channel;
use App\Models\Server;

interface RTMPStreamerInterface
{
    public function startStream(Stream $stream, Channel $channel, Server $server): void;

    public function stopStream(Stream $stream): void;

    public function getStreamUrl(Stream $stream): string;

    public function getPlayUrl(Stream $stream): string;

    public function isStreamActive(Stream $stream): bool;

    public function getActiveStreams(): array;

    public function publishStream(Stream $stream, string $streamKey): bool;

    public function unpublishStream(Stream $stream): void;

    public function getStreamStats(Stream $stream): array;

    public function validateStreamKey(string $streamKey): bool;
}
