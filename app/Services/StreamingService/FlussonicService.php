<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Flussonic REST API.
 *
 * Flussonic API reference used:
 *   PUT  /streamer/api/v3/streams/{name}   — create or update a stream
 *   GET  /streamer/api/v3/streams/{name}   — check if a stream exists
 *
 * A stream name is derived from the channel slug / id so it is stable across
 * restarts and can be looked up without storing a separate Flussonic ID.
 */
class FlussonicService
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('streaming.flussonic.url', 'http://localhost:8080'), '/');
        $this->username = (string) config('streaming.flussonic.username', 'flussonic');
        $this->password = (string) config('streaming.flussonic.password', '');
    }

    /**
     * Ensure a stream exists in Flussonic for the given UDP source.
     *
     * Creates the stream if it does not exist yet; updates the input URL if it
     * does. Returns the Flussonic HLS playlist URL for this stream so the
     * caller can store it as the channel's stream_url.
     *
     * @param  string      $name          Unique stream name (e.g. "ch-42")
     * @param  string      $udpUrl        Full udp://@239.x.x.x:port URL
     * @param  int|null    $programNumber MPEG-TS program number (null = whole mux)
     * @param  string|null $localAddress  NIC IP for multicast join
     * @return string                     Flussonic HLS URL for this stream
     */
    /**
     * Push one program from a UDP multicast mux into Flussonic.
     * Flussonic joins the multicast group once per unique URL and demuxes
     * each program internally — no extra ffmpeg process needed.
     *
     * Returns the Flussonic HLS URL for this stream.
     */
    public function ensureStream(
        string $name,
        string $udpUrl,
        ?int $programNumber = null,
        ?string $localAddress = null
    ): string {
        $input = $this->buildUdpInput($udpUrl, $programNumber, $localAddress);

        // Flussonic API v3: the input url field accepts the full directive string
        // including space-separated options like "pnr=N". Split into url + options
        // so the API receives them in the correct fields.
        [$inputUrl, $inputOptions] = array_pad(explode(' ', $input, 2), 2, null);

        $inputPayload = ['url' => $inputUrl];
        if ($inputOptions !== null && $inputOptions !== '') {
            $inputPayload['options'] = $inputOptions;
        }

        $payload = [
            'name'   => $name,
            'inputs' => [$inputPayload],
            'static' => true,
        ];

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(10)
            ->put("{$this->baseUrl}/streamer/api/v3/streams/{$name}", $payload);

        if (! $response->successful()) {
            Log::error('Flussonic stream upsert failed', [
                'name'   => $name,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException(
                "Flussonic API error {$response->status()} for stream '{$name}': " . $response->body()
            );
        }

        Log::info('Flussonic stream ensured', ['name' => $name, 'input' => $input]);

        return "{$this->baseUrl}/{$name}/index.m3u8";
    }

    /**
     * Build the Flussonic UDP input string.
     *
     * Flussonic native syntax:
     *   udp://LOCAL_IP@MULTICAST_IP:PORT pnr=N
     *
     * - localaddr is expressed as LOCAL_IP@ prefix (not a query param)
     * - pnr is a space-separated directive (NOT ?pnr=N query string)
     *
     * Example: udp://192.168.101.254@228.101.17.1:2001 pnr=22
     */
    public function buildUdpInput(string $udpUrl, ?int $programNumber, ?string $localAddress): string
    {
        // Strip any legacy query params (?localaddr=, ?pnr=, &pnr=, etc.)
        $base = preg_replace('/[?&](localaddr|program|pnr)=[^&\s]*/i', '', $udpUrl);
        $base = rtrim($base, '?& ');

        // Normalise udp://@multicast:port  →  udp://LOCAL_IP@multicast:port
        // If localAddress is provided, replace the leading @ with LOCAL_IP@
        if ($localAddress !== null && $localAddress !== '') {
            // Handle udp://@host:port  →  udp://LOCAL_IP@host:port
            $base = preg_replace('#^(udp|rtp)://@#i', '$1://' . $localAddress . '@', $base);

            // Handle udp://host:port (no @) → udp://LOCAL_IP@host:port
            if (!str_contains($base, '@')) {
                $base = preg_replace('#^(udp|rtp)://#i', '$1://' . $localAddress . '@', $base);
            }
        }

        // Append pnr as a space-separated directive — Flussonic's required format
        if ($programNumber !== null && $programNumber > 0) {
            $base .= ' pnr=' . $programNumber;
        }

        return $base;
    }

    /**
     * Delete a stream from Flussonic (used when a channel is removed).
     */
    public function deleteStream(string $name): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(10)
            ->delete("{$this->baseUrl}/streamer/api/v3/streams/{$name}");
        return $response->successful();
    }

    /**
     * Check if Flussonic API is reachable at all.
     */
    public function isReachable(): bool
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(3)
                ->get("{$this->baseUrl}/streamer/api/v3/streams");
            return $response->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check whether Flussonic already has a stream with this name.
     */
    public function streamExists(string $name): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(5)
            ->get("{$this->baseUrl}/streamer/api/v3/streams/{$name}");

        return $response->successful();
    }

    /**
     * Return live stats for a stream from Flussonic.
     *
     * Relevant fields in the response:
     *   alive        — bool, true when Flussonic has an active input
     *   input_bitrate — current ingest bitrate in bits/s (0 when frozen)
     *   last_dts      — last decoded timestamp (unix ms); stops advancing when frozen
     *
     * Returns null when the stream is not found or the API is unreachable.
     */
    public function getStreamStats(string $name): ?array
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(5)
            ->get("{$this->baseUrl}/streamer/api/v3/streams/{$name}");

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Tell Flussonic to restart (reconnect) the input for a stream.
     *
     * Flussonic v22+ exposes DELETE /streamer/api/v3/streams/{name}/input
     * which drops the current input connection so Flussonic immediately
     * reconnects. This is lighter than a full stream restart.
     */
    public function restartStreamInput(string $name): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(10)
            ->delete("{$this->baseUrl}/streamer/api/v3/streams/{$name}/input");

        if ($response->successful()) {
            Log::info('Flussonic stream input restarted', ['name' => $name]);
            return true;
        }

        // Fallback: full stream restart via PUT with same config re-applied
        $stats = $this->getStreamStats($name);
        if ($stats === null) {
            return false;
        }

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(10)
            ->put("{$this->baseUrl}/streamer/api/v3/streams/{$name}", $stats);

        if ($response->successful()) {
            Log::info('Flussonic stream restarted via PUT', ['name' => $name]);
            return true;
        }

        Log::error('Flussonic stream restart failed', [
            'name'   => $name,
            'status' => $response->status(),
        ]);

        return false;
    }
}
