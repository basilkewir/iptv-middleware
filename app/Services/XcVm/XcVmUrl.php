<?php

namespace App\Services\XcVm;

use Illuminate\Support\Str;

/**
 * URL helpers that bridge the middleware's local storage layout with the
 * XC-VM engine whose VOD fetcher and player live on the private loopback.
 */
class XcVmUrl
{
    /**
     * Resolve a local VOD media path into a URL XC-VM can reach.
     *
     * Accepted inputs, in order:
     *   1. absolute http(s) URL            -> passed through untouched
     *   2. "/storage/vod/x.mp4"            -> {base}/storage/vod/x.mp4
     *   3. "storage/vod/x.mp4"             -> {base}/storage/vod/x.mp4
     *   4. "app/public/vod/x.mp4"          -> {base}/storage/vod/x.mp4
     *
     * {base} is config('xcvm.vod_url_base'). When it is blank, translation is
     * disabled and the raw value is returned so the caller can still store it.
     *
     * Returns null only when the value is empty or not a path/URL we can map.
     */
    public static function vod(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $raw)) {
            return $raw;
        }

        $base = rtrim((string) config('xcvm.vod_url_base'), '/');

        if ($base === '') {
            return $raw;
        }

        if (Str::startsWith($raw, 'storage/app/public/')) {
            $raw = '/storage/' . substr($raw, strlen('storage/app/public/'));
        } elseif (Str::startsWith($raw, 'storage/app/')) {
            $raw = '/storage/' . substr($raw, strlen('storage/app/'));
        }

        return $raw[0] === '/'
            ? $base . $raw
            : $base . '/' . $raw;
    }

    /**
     * Public URL of a local VOD file as reachable from players (not XC-VM).
     * Uses the middleware's own app.url so the local client-facing server is
     * always the one serving the bytes.
     */
    public static function publicVod(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $raw)) {
            return $raw;
        }

        if (Str::startsWith($raw, 'storage/app/public/')) {
            $raw = substr($raw, strlen('storage/app/public/'));
        } elseif (Str::startsWith($raw, '/storage/')) {
            $raw = substr($raw, strlen('/storage/'));
        } else {
            return $raw;
        }

        return rtrim((string) config('app.url'), '/') . '/storage/' . $raw;
    }

    /**
     * Build an internal XC-VM player URL used when proxying a stream through
     * the middleware. $endpoint must begin with a leading slash, e.g.
     * "/live/{username}/{password}/{remoteId}.m3u8".
     *
     * Xtream stream routes (/live/…, /movie/…, /series/…) live at the root of
     * the panel; there is no access-code path segment on them.
     */
    public static function player(string $endpoint): string
    {
        $url = rtrim((string) config('xcvm.proxy_url', config('xcvm.url')), '/');

        $port = (int) config('xcvm.proxy_port', config('xcvm.port'));
        if ($port > 0 && $port !== 80 && ! str_contains($url, ':') && ! str_ends_with($url, '/')) {
            $url .= ":{$port}";
        }

        return $url . '/' . ltrim($endpoint, '/');
    }

    /**
     * The base middleware URL XC-VM reaches us on (used by install.sh to keep
     * the VOD bridge in sync with APP_URL).
     */
    public static function middlewareBase(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}