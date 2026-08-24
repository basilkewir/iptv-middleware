<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HlsController extends Controller
{
    private string $segmentRoot;

    public function __construct()
    {
        $this->segmentRoot = storage_path('app/streams/hls');
    }

    /**
     * Serve streaming segments/playlists for a broadcast key.
     * Matches URL: /hls/{key}/{file}
     */
    public function serve(Request $request, string $key, string $file)
    {
        $key = basename(trim($key));
        $file = basename(trim($file));

        if ($key === '' || $key === '.' || $key === '..' || $file === '') {
            abort(404);
        }

        $streamDir = "{$this->segmentRoot}/{$key}";

        if (! File::isDirectory($streamDir)) {
            abort(404, 'Stream not found');
        }

        $allowed = ['m3u8', 'ts', 'aac'];
        $extension = strtolower(File::extension($file));

        if (! in_array($extension, $allowed, true)) {
            abort(403, 'File type not allowed');
        }

        $absolute = realpath("{$streamDir}/{$file}");

        // Path traversal check: realpath must resolve AND stay inside the
        // stream directory.  If the file simply doesn't exist yet (ingest
        // starting up), realpath() returns false — fall through to the
        // existence check below so stale-cache / 503 logic can apply.
        if ($absolute !== false && ! str_starts_with($absolute, realpath($streamDir))) {
            abort(403, 'Invalid stream path');
        }

        if (! File::exists("{$streamDir}/{$file}")) {
            // Ingest is restarting — serve stale content from cache instead
            // of 503/404, which freezes Android TV players. ExoPlayer and
            // most IPTV players handle stale-but-valid playlists gracefully.

            if ($extension === 'm3u8') {
                $cacheKey = "hls:stale:{$key}:playlist";
                $cached = Cache::get($cacheKey);

                if ($cached !== null) {
                    return response($cached, 200, [
                        'Content-Type'              => 'application/vnd.apple.mpegurl',
                        'Cache-Control'             => 'no-cache, no-store, must-revalidate',
                        'Access-Control-Allow-Origin'=> '*',
                        'X-HLS-Stale'               => '1',
                    ]);
                }

                // No cached playlist — tell the player to retry in 3s
                return response('Service Unavailable', 503, [
                    'Retry-After'                => '3',
                    'Cache-Control'              => 'no-cache, no-store, must-revalidate',
                    'Access-Control-Allow-Origin'=> '*',
                ]);
            }

            // For .ts segments: return 204 No Content instead of 404.
            // 404 makes ExoPlayer think the stream ended; 204 just means
            // "no data yet" and the player keeps polling.
            return response('', 204, [
                'Cache-Control'              => 'no-cache, no-store, must-revalidate',
                'Access-Control-Allow-Origin'=> '*',
            ]);
        }

        // Cache successful playlists for stale serving during restarts
        if ($extension === 'm3u8') {
            $content = file_get_contents($absolute);
            if ($content !== false && strlen($content) > 10) {
                Cache::put("hls:stale:{$key}:playlist", $content, 30);
            }
        }

        $mime = $extension === 'm3u8'
            ? 'application/vnd.apple.mpegurl'
            : 'video/mp2t';

        $response = new StreamedResponse(function () use ($absolute) {
            $handle = fopen($absolute, 'rb');
            if ($handle === false) {
                return;
            }

            fpassthru($handle);
            fclose($handle);
        });

        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}