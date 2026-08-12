<?php

declare(strict_types=1);

namespace App\Contracts\Streaming;

use App\Models\Stream;
use App\Models\Channel;
use App\Models\Server;

interface HLSStreamerInterface
{
    public function startStream(Stream $stream, Channel $channel, Server $server): void;

    public function stopStream(Stream $stream): void;

    public function getStreamPath(Stream $stream): string;

    public function getSegmentUrl(Stream $stream, string $segmentName): string;

    public function getPlaylistUrl(Stream $stream): string;

    public function getActiveSegments(Stream $stream): array;

    public function cleanExpiredSegments(Stream $stream, int $maxAge = 300): int;

    public function updatePlaylist(Stream $stream): void;

    public function validateStream(string $streamKey): bool;
}
