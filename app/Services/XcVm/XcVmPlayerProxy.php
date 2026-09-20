<?php

namespace App\Services\XcVm;

use App\Models\XcVmMapping;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Transparent player proxy.
 *
 * When XC_VM_PROXY_PLAYER=true the middleware authenticates every player
 * request itself (source of truth) and then pulls the stream from the
 * private-loopback XC-VM panel, streaming the bytes back through the
 * middleware so no XC-VM port is ever exposed. Returns null when XC-VM is
 * unreachable or the entity is not mapped yet, letting the caller fall back
 * to the built-in HLS ingest / local VOD file serving.
 */
class XcVmPlayerProxy
{
    public function __construct(protected GuzzleClient $http)
    {
    }

    public static function enabled(): bool
    {
        return (bool) config('xcvm.proxy_player', false);
    }

    /**
     * Whether XC-VM is healthy and can serve streams.
     * Returns false when the circuit breaker is open or XC-VM is not configured.
     */
    public static function xcVmAvailable(): bool
    {
        if (! self::enabled()) {
            return false;
        }

        return app(\App\Services\XcVm\XcVmClient::class)->isHealthy();
    }

    /**
     * Map a middleware entity to its XC-VM id.
     */
    public function remoteId(string $type, int $localId): ?int
    {
        return XcVmMapping::lookup($type, $localId);
    }

    public function proxyEnabledAndMapped(string $type, int $localId): bool
    {
        return self::enabled() && $this->remoteId($type, $localId) !== null;
    }

    /**
     * Try to proxy {$type:$localId} from the XC-VM player.
     *
     * $suffix, when given, is appended to the XC-VM stream path untouched
     * (e.g. ".m3u8" or ".ts"). The upstream response is returned as a Laravel
     * response; HLS playlists are buffered and rewritten so segment URLs point
     * back at the middleware; everything else (segments, files) is streamed
     * through chunk by chunk with byte-range passthrough.
     *
     * Returns null when the entity has no mapping or XC-VM does not answer.
     */
    public function tryStream(
        string $type,
        int $localId,
        string $username,
        string $password,
        ?string $suffix = null
    ): mixed {
        if (! self::enabled()) {
            return null;
        }

        if (! self::xcVmAvailable()) {
            return null;
        }

        $remoteId = $this->remoteId($type, $localId);
        if ($remoteId === null) {
            return null;
        }

        $path = $this->playerPath($type, $username, $password, $remoteId, $suffix);
        $url = XcVmUrl::player($path);

        if ($this->isPlaylistUrl($url)) {
            return $this->proxyPlaylist($url, $username, $password, $remoteId, $type);
        }

        return $this->proxyStreaming($url);
    }

    protected function playerPath(string $type, string $username, string $password, int $remoteId, ?string $suffix): string
    {
        $resource = match ($type) {
            'channel', 'admin_channel' => 'live',
            'vod', 'movie' => 'movie',
            'series', 'episode' => 'series',
            default => 'live',
        };

        return "/{$resource}/{$username}/{$password}/{$remoteId}" . ($suffix ?: '');
    }

    protected function isPlaylistUrl(string $url): bool
    {
        return str_contains($url, '.m3u8');
    }

    /**
     * Fetch an HLS playlist from XC-VM, rewrite segment references back to the
     * middleware, and return it as a normal response.
     */
    protected function proxyPlaylist(string $url, string $username, string $password, int $remoteId, string $type): mixed
    {
        $response = $this->fetch($url);

        if ($response === null) {
            return null;
        }

        $headers = $this->passHeaders($response, ['Content-Type' => 'application/vnd.apple.mpegurl', 'Cache-Control' => 'no-cache']);
        $content = $this->rewritePlaylist((string) $response->getBody(), $username, $password, $remoteId);

        return response($content, $response->getStatusCode(), $headers);
    }

    /**
     * Stream a non-playlist resource from XC-VM through chunk by chunk.
     * Returns 204 on XC-VM errors instead of propagating 4xx/5xx, which
     * keeps players alive during brief XC-VM hiccups.
     */
    protected function proxyStreaming(string $url): mixed
    {
        $response = $this->fetch($url);

        if ($response === null) {
            // Return 204 No Content — players keep polling instead of
            // showing a "playback error" dialog.
            return response('', 204, [
                'Cache-Control' => 'no-cache, no-store',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $status = $response->getStatusCode();
        $headers = collect($response->getHeaders())
            ->only(['content-type', 'content-range', 'accept-ranges'])
            ->mapWithKeys(fn ($v, $k) => [$k => $v[0] ?? ''])
            ->all();

        $headers['Content-Type'] ??= 'application/octet-stream';
        $headers['Accept-Ranges'] ??= 'bytes';
        $headers['Cache-Control'] ??= 'no-cache';

        $body = $response->getBody();

        return response()->stream(
            function () use ($body) {
                while (! $body->eof()) {
                    echo $body->read(2 * 1024 * 1024); // 2MB chunks for better throughput
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            },
            $status,
            $headers
        );
    }

    /**
     * Rewrite an XC-VM HLS playlist so that every media reference flows back
     * through the middleware (/live/u/p/…), regardless of whether XC-VM emits
     * absolute (own-host) or bare relative segment names.
     *
     * Handles:
     *   - Absolute URLs with upstream host
     *   - Absolute paths (/live/..., /movie/..., /series/...)
     *   - Bare segment filenames (456.ts)
     *   - Variant/master playlists (#EXT-X-STREAM-INF)
     *   - Media rendition URIs (#EXT-X-MEDIA URI=)
     *   - Query strings on segment URLs (token-based auth preservation)
     */
    protected function rewritePlaylist(string $content, string $username, string $password, int $remoteId): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        // Strip the upstream base URL prefix (scheme + host + port)
        $content = str_replace(["{$this->upstreamBase()}/", "{$this->upstreamBase()} "], "{$appUrl}/", $content);

        // Absolute /live /movie /series paths without a host.
        $content = preg_replace(
            '#(^|[\s"\',])(/(?:live|movie|series)/)#m',
            '$1' . $appUrl . '$2',
            $content
        );

        // Bare segment names, e.g. "456.ts" or "456.m3u8".
        // Only match when preceded by a quote, space, or line start to avoid
        // breaking other numeric values in the playlist.
        $escapedRemoteId = preg_quote((string) $remoteId, '#');
        $content = preg_replace(
            '#([\s"\',/])' . $escapedRemoteId . '\.(ts|m3u8)(?=[\s"\'<;?]|$)#m',
            '$1' . $appUrl . "/live/{$username}/{$password}/{$remoteId}.\$2",
            $content
        );

        // Handle #EXT-X-MEDIA URI= tags (audio/subtitle rendition playlists)
        $content = preg_replace(
            '#(URI=")(/(?:live|movie|series)/[^"]+)(")#',
            '$1' . $appUrl . '$2$3',
            $content
        );

        // Handle #EXT-X-STREAM-INF URI= tags (multi-bitrate master playlists)
        $content = preg_replace(
            '#(URI=")([^"]+\.m3u8[^"]*)(")#',
            function ($m) use ($appUrl) {
                $uri = $m[2];
                // Only rewrite if it's a relative or upstream-host URL
                if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
                    return $m[0]; // Already absolute, leave alone
                }
                return $m[1] . $appUrl . '/' . ltrim($uri, '/') . $m[3];
            },
            $content
        );

        return $content;
    }

    protected function upstreamBase(): string
    {
        $url = rtrim((string) config('xcvm.proxy_url', config('xcvm.url')), '/');
        $port = (int) config('xcvm.proxy_port', config('xcvm.port'));

        if ($port > 0 && $port !== 80 && ! str_contains($url, ':') && ! str_ends_with($url, '/')) {
            $url .= ":{$port}";
        }

        return $url;
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    protected function fetch(string $url): mixed
    {
        try {
            $options = [
                'stream' => true,
                'timeout' => (float) config('xcvm.proxy_timeout', 30),
                'connect_timeout' => 10,
                'http_errors' => false,
                'allow_redirects' => true,
                'headers' => ['User-Agent' => 'iptv-middleware-xcvm-proxy/1.0'],
            ];

            $range = $_SERVER['HTTP_RANGE'] ?? null;
            if ($range) {
                $options['headers']['Range'] = $range;
            }

            $response = $this->http->get($url, $options);

            if ($response->getStatusCode() >= 400) {
                Log::warning("XC-VM player proxy upstream error for [{$url}]", [
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }

            return $response;
        } catch (GuzzleException $e) {
            Log::warning("XC-VM player proxy unreachable for [{$url}]: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * @param  array<string, string|array<int, string>>  $defaults
     * @return array<string, string>
     */
    protected function passHeaders(mixed $response, array $defaults): array
    {
        $headers = [];
        foreach (['content-type', 'content-range', 'accept-ranges', 'content-length', 'cache-control'] as $name) {
            if ($response->hasHeader($name)) {
                $headers[$name] = $response->getHeaderLine($name);
            }
        }

        return array_replace($defaults, $headers);
    }
}