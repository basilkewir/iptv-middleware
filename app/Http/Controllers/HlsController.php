<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        if ($absolute === false || ! str_starts_with($absolute, realpath($streamDir))) {
            abort(403, 'Invalid stream path');
        }

        if (! File::exists($absolute)) {
            abort(404, 'Segment not found (yet)');
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