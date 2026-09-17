<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class OfflineVideoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/OfflineVideo', [
            'status' => $this->buildStatus(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/x-matroska,video/quicktime,video/x-msvideo,video/webm|max:512000',
        ]);

        $dir = storage_path('app/offline');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $request->file('video')->move($dir, 'channel-offline.mp4');

        return back()->with('success', 'Video uploaded. Click "Prepare HLS Stream" to apply it.');
    }

    public function prepare(): RedirectResponse
    {
        $videoPath = config('streaming.offline.video_path');

        if (! is_file($videoPath)) {
            return back()->with('error', 'No offline video found. Please upload a video first.');
        }

        Artisan::call('streams:prepare-offline');

        return back()->with('success', 'Offline HLS stream is being generated in the background.');
    }

    public function destroy(): RedirectResponse
    {
        $videoPath = config('streaming.offline.video_path');
        $hlsDir    = config('streaming.offline.hls_dir');

        if (is_file($videoPath)) {
            unlink($videoPath);
        }

        if (is_dir($hlsDir)) {
            array_map('unlink', glob("{$hlsDir}/*.ts") ?: []);
            @unlink("{$hlsDir}/playlist.m3u8");
        }

        return back()->with('success', 'Offline video and HLS stream removed.');
    }

    private function buildStatus(): array
    {
        $videoPath = config('streaming.offline.video_path');
        $hlsDir    = config('streaming.offline.hls_dir');
        $playlist  = "{$hlsDir}/playlist.m3u8";

        $hasVideo   = is_file($videoPath);
        $hlsReady   = is_file($playlist);
        $segCount   = $hlsReady ? count(glob("{$hlsDir}/*.ts") ?: []) : 0;
        $videoSize  = $hasVideo ? round(filesize($videoPath) / 1048576, 1) : null;
        $preparedAt = $hlsReady ? date('Y-m-d H:i:s', filemtime($playlist)) : null;

        return [
            'has_video'    => $hasVideo,
            'video_size_mb'=> $videoSize,
            'hls_ready'    => $hlsReady,
            'segment_count'=> $segCount,
            'prepared_at'  => $preparedAt,
            'stream_url'   => $hlsReady ? config('app.url').'/hls/offline/playlist.m3u8' : null,
        ];
    }
}
