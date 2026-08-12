<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\VOD;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function liveChannel(Request $request, string $slug)
    {
        $channel = Channel::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $channel->is_free && ! auth()->check()) {
            abort(403, 'Please subscribe to access this channel.');
        }

        if (! $channel->is_free && auth()->check()) {
            $subscription = auth()->user()->activeSubscription();

            if (! $subscription) {
                abort(403, 'Active subscription required.');
            }
        }

        return view('public.stream.channel', compact('channel'));
    }

    public function vod(Request $request, string $slug)
    {
        $vod = VOD::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $vod->is_free && ! auth()->check()) {
            abort(403, 'Please subscribe to access this content.');
        }

        if (! $vod->is_free && auth()->check()) {
            $subscription = auth()->user()->activeSubscription();

            if (! $subscription) {
                abort(403, 'Active subscription required.');
            }
        }

        return view('public.stream.vod', compact('vod'));
    }

    public function playlist(Request $request, string $format)
    {
        $channels = Channel::where('is_active', true)->get();

        if ($format === 'm3u') {
            $content = "#EXTM3U\n";

            foreach ($channels as $channel) {
                $groupName = $channel->category->name ?? 'General';
                $epgId = $channel->epg_id ?? '';
                $logo = $channel->logo ?? '';
                $content .= "#EXTINF:-1 tvg-id=\"{$epgId}\" tvg-logo=\"{$logo}\" group-title=\"{$groupName}\",{$channel->name}\n";
                $content .= "{$channel->stream_url}\n";
            }

            return response($content, 200, [
                'Content-Type' => 'application/x-mpegurl',
                'Content-Disposition' => 'attachment; filename="playlist.m3u"',
            ]);
        }

        if ($format === 'txt') {
            $content = "";

            foreach ($channels as $channel) {
                $content .= "#EXTINF:-1,{$channel->name}\n";
                $content .= "{$channel->stream_url}\n";
            }

            return response($content, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="playlist.txt"',
            ]);
        }

        abort(404);
    }
}
