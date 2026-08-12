<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\User;
use App\Models\VODContent;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    /**
     * Generate M3U playlist for a client using their token.
     * URL: /playlist/{token}/m3u
     */
    public function generate(Request $request, string $token)
    {
        $user = User::where('m3u_token', $token)
            ->where('is_active', true)
            ->where('role', 'client')
            ->first();

        if (!$user) {
            return response('Unauthorized - Invalid or expired token', 401);
        }

        // Check subscription is active
        $activeSub = $user->activeSubscription();
        if (!$activeSub) {
            return response('Subscription expired or inactive', 403);
        }

        $base = rtrim(config('app.url'), '/');
        $lines = ['#EXTM3U'];

        // Get channels from assigned bouquets
        $bouquetIds = $user->bouquets()->pluck('bouquets.id');
        
        if ($bouquetIds->isNotEmpty()) {
            $channels = Channel::whereHas('bouquets', function ($q) use ($bouquetIds) {
                $q->whereIn('bouquets.id', $bouquetIds);
            })->where('is_active', true)->orderBy('channel_number')->get();
        } else {
            // If no bouquets assigned, give all channels
            $channels = Channel::where('is_active', true)->orderBy('channel_number')->get();
        }

        foreach ($channels as $ch) {
            $groupName = $ch->categories->first()?->name ?? 'Uncategorized';
            $lines[] = sprintf(
                '#EXTINF:-1 tvg-id="%s" tvg-name="%s" tvg-logo="%s" group-title="%s",%s',
                $ch->epg_channel_id ?? '',
                $ch->name,
                $ch->logo_url ?? '',
                $groupName,
                $ch->name
            );
            $lines[] = "{$base}/live/{$user->username}/{$token}/{$ch->id}";
        }

        // Add VOD content if available
        $vods = VODContent::where('is_active', true)->where('type', 'movie')
            ->orderBy('title')->get();
        foreach ($vods as $v) {
            $media = $v->mediaFiles()->first();
            $ext = $media?->format ?? 'mp4';
            $groupName = $v->categories->first()?->name ?? 'Movies';
            $lines[] = sprintf(
                '#EXTINF:-1 tvg-id="" tvg-name="%s" tvg-logo="%s" group-title="%s",%s',
                $v->title,
                $v->poster_url ?? '',
                $groupName,
                $v->title
            );
            $lines[] = "{$base}/movie/{$user->username}/{$token}/{$v->id}.{$ext}";
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/x-mpegurl',
            'Content-Disposition' => 'attachment; filename="playlist.m3u"',
        ]);
    }
}