<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\EPGProgram;
use App\Models\User;
use App\Models\VODContent;
use App\Models\VODMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class XtreamController extends Controller
{
    // Authenticate and return user/server info (Xtream Codes protocol)
    public function auth(Request $request)
    {
        $user = $this->authenticate($request);

        if (! $user) {
            return response()->json(['user_info' => null, 'server_info' => null], 401);
        }

        return response()->json([
            'user_info' => $this->userInfo($user),
            'server_info' => $this->serverInfo($request),
        ]);
    }

    // Live streams list
    public function liveStreams(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $channels = Channel::with('categories')->where('is_active', true)
            ->orderBy('channel_number')
            ->get()
            ->map(fn ($ch) => [
                'num'            => $ch->channel_number,
                'name'           => $ch->name,
                'stream_type'    => 'live',
                'stream_id'      => $ch->id,
                'stream_icon'    => $ch->logo_url ?? '',
                'epg_channel_id' => $ch->epg_channel_id ?? '',
                'added'          => (string) $ch->created_at?->timestamp,
                'category_id'    => $ch->categories->first()?->id ?? '',
                'custom_sid'     => '',
                'tv_archive'     => 0,
                'direct_source'  => '',
                'tv_archive_duration' => 0,
            ]);

        return response()->json($channels);
    }

    // VOD streams list
    public function vodStreams(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $vods = VODContent::where('is_active', true)
            ->where('type', 'movie')
            ->with(['vodMedia', 'categories'])
            ->latest()
            ->get()
            ->map(fn ($v) => [
                'num'           => $v->id,
                'name'          => $v->title,
                'stream_type'   => 'movie',
                'stream_id'     => $v->id,
                'stream_icon'   => $v->poster_url ?? '',
                'rating'        => (string) $v->rating,
                'rating_5based' => round($v->rating / 2, 1),
                'added'         => (string) $v->created_at?->timestamp,
                'category_id'   => $v->categories->first()?->id ?? '',
                'container_extension' => $v->vodMedia->first()?->stream_type ?? 'mp4',
                'custom_sid'    => '',
                'direct_source' => '',
            ]);

        return response()->json($vods);
    }

    // Series list
    public function series(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $series = VODContent::where('is_active', true)
            ->where('type', 'series')
            ->with('categories')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'num'          => $s->id,
                'name'         => $s->title,
                'series_id'    => $s->id,
                'cover'        => $s->poster_url ?? '',
                'plot'         => $s->description ?? '',
                'cast'         => is_array($s->cast) ? implode(', ', $s->cast) : ($s->cast ?? ''),
                'director'     => $s->director ?? '',
                'genre'        => is_array($s->genre) ? implode(', ', $s->genre) : ($s->genre ?? ''),
                'releaseDate'  => $s->year ?? '',
                'last_modified'=> (string) $s->updated_at?->timestamp,
                'rating'       => (string) $s->rating,
                'rating_5based'=> round($s->rating / 2, 1),
                'backdrop_path'=> $s->backdrop_url ? [$s->backdrop_url] : [],
                'youtube_trailer' => $s->trailer_url ?? '',
                'episode_run_time' => '',
                'category_id'  => $s->categories->first()?->id ?? '',
            ]);

        return response()->json($series);
    }

    // Live stream categories
    public function liveCategories(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $cats = ContentCategory::where('is_active', true)
            ->whereHas('channels')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'category_id'   => (string) $c->id,
                'category_name' => $c->name,
                'parent_id'     => 0,
            ]);

        return response()->json($cats);
    }

    // VOD categories
    public function vodCategories(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $cats = ContentCategory::where('is_active', true)
            ->whereHas('vodContent')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'category_id'   => (string) $c->id,
                'category_name' => $c->name,
                'parent_id'     => 0,
            ]);

        return response()->json($cats);
    }

    // Series categories
    public function seriesCategories(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $cats = ContentCategory::where('is_active', true)
            ->whereHas('vodContent', fn ($q) => $q->where('type', 'series'))
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'category_id'   => (string) $c->id,
                'category_name' => $c->name,
                'parent_id'     => 0,
            ]);

        return response()->json($cats);
    }

    // Series info with seasons/episodes
    public function seriesInfo(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $series = VODContent::with(['vodMedia', 'categories'])->find($request->series_id);
        if (! $series) return response()->json([]);

        $episodes = [];
        foreach ($series->vodMedia as $ep) {
            $season = $ep->season_number ?? 1;
            $episodes[$season][] = [
                'id'             => (string) $ep->id,
                'episode_num'    => $ep->episode_number ?? 1,
                'title'          => $ep->title ?? $series->title,
                'container_extension' => $ep->stream_type ?? 'mp4',
                'info' => [
                    'duration_secs' => $ep->duration ?? 0,
                    'duration'      => gmdate('H:i:s', $ep->duration ?? 0),
                    'video' => [],
                    'audio' => [],
                ],
                'custom_sid'     => '',
                'added'          => (string) $ep->created_at?->timestamp,
                'season'         => $season,
                'direct_source'  => '',
            ];
        }

        return response()->json([
            'seasons' => [],
            'info' => [
                'name'          => $series->title,
                'cover'         => $series->poster_url ?? '',
                'plot'          => $series->description ?? '',
                'cast'          => is_array($series->cast) ? implode(', ', $series->cast) : ($series->cast ?? ''),
                'director'      => $series->director ?? '',
                'genre'         => is_array($series->genre) ? implode(', ', $series->genre) : ($series->genre ?? ''),
                'releaseDate'   => $series->year ?? '',
                'backdrop_path' => $series->backdrop_url ? [$series->backdrop_url] : [],
                'youtube_trailer' => $series->trailer_url ?? '',
                'episode_run_time' => '',
                'category_id'   => $series->categories->first()?->id ?? '',
                'rating'        => (string) $series->rating,
            ],
            'episodes' => $episodes,
        ]);
    }

    // VOD info
    public function vodInfo(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $vod = VODContent::with(['vodMedia', 'categories'])->find($request->vod_id);
        if (! $vod) return response()->json([]);

        $media = $vod->vodMedia->first();

        return response()->json([
            'info' => [
                'kinopoisk_url' => '',
                'tmdb_id'       => $vod->tmdb_id ?? '',
                'name'          => $vod->title,
                'o_name'        => $vod->title,
                'cover_big'     => $vod->backdrop_url ?? $vod->poster_url ?? '',
                'movie_image'   => $vod->poster_url ?? '',
                'releasedate'   => $vod->year ?? '',
                'episode_run_time' => $vod->duration ?? 0,
                'youtube_trailer' => $vod->trailer_url ?? '',
                'director'      => $vod->director ?? '',
                'actors'        => is_array($vod->cast) ? implode(', ', $vod->cast) : ($vod->cast ?? ''),
                'cast'          => is_array($vod->cast) ? implode(', ', $vod->cast) : ($vod->cast ?? ''),
                'description'   => $vod->description ?? '',
                'plot'          => $vod->description ?? '',
                'age'           => '',
                'mpaa_rating'   => '',
                'rating_count_kinopoisk' => 0,
                'country'       => '',
                'genre'         => is_array($vod->genre) ? implode(', ', $vod->genre) : ($vod->genre ?? ''),
                'backdrop_path' => $vod->backdrop_url ? [$vod->backdrop_url] : [],
                'duration_secs' => $vod->duration ?? 0,
                'duration'      => gmdate('H:i:s', $vod->duration ?? 0),
                'bitrate'       => 0,
                'rating'        => (string) $vod->rating,
                'status'        => 'Active',
            ],
            'movie_data' => [
                'stream_id'           => $vod->id,
                'name'                => $vod->title,
                'added'               => (string) $vod->created_at?->timestamp,
                'category_id'         => $vod->categories->first()?->id ?? '',
                'container_extension' => $media?->stream_type ?? 'mp4',
                'custom_sid'          => '',
                'direct_source'       => '',
            ],
        ]);
    }

    // EPG for a channel
    public function epg(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json(['epg_listings' => []]);

        $channel = Channel::find($request->stream_id);
        if (! $channel) return response()->json(['epg_listings' => []]);

        $programs = EPGProgram::where('channel_id', $channel->id)
            ->where('end_time', '>=', now())
            ->orderBy('start_time')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id'          => (string) $p->id,
                'epg_id'      => $channel->epg_channel_id ?? '',
                'title'       => base64_encode($p->title),
                'lang'        => 'en',
                'start'       => $p->start_time?->format('Y-m-d H:i:s'),
                'end'         => $p->end_time?->format('Y-m-d H:i:s'),
                'description' => base64_encode($p->description ?? ''),
                'channel_id'  => $channel->epg_channel_id ?? '',
                'start_timestamp' => (string) $p->start_time?->timestamp,
                'stop_timestamp'  => (string) $p->end_time?->timestamp,
                'now_playing' => $p->start_time <= now() && $p->end_time >= now() ? 1 : 0,
                'has_archive' => 0,
            ]);

        return response()->json(['epg_listings' => $programs]);
    }

    // Stream a live channel  
    public function streamLive(Request $request, $username, $password, $streamId)
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) {
            abort(401);
        }

        // Accept either the password hash or the m3u_token
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) {
            abort(401);
        }

        // Extract numeric ID from streamId (e.g., "1.m3u8" -> 1)
        $channelId = (int) $streamId;

        $channel = Channel::where('id', $channelId)->where('is_active', true)->firstOrFail();

        // Serve via local HLS — never expose or redirect to the upstream source URL
        $hlsPath = storage_path("app/streams/hls/{$channelId}/playlist.m3u8");

        if (! file_exists($hlsPath)) {
            // Start the FFmpeg ingest if not already running
            $this->ensureHlsStream($channelId, $channel->stream_url);
        }

        return redirect("/hls/{$channelId}/playlist.m3u8");
    }

    /**
     * Ensure an HLS ingest process is running for the given channel.
     * Tracks the ingest by a per-channel PID file so each channel is ingested
     * exactly once and dead ingests can be respawned without scanning every
     * process (used by both on-demand playback and channels:ingest-all).
     */
    public function ensureHlsStream(int $channelId, string $sourceUrl): void
    {
        $outputDir = storage_path("app/streams/hls/{$channelId}");
        $pidFile = $outputDir . '/ingest.pid';

        if (is_file($pidFile)) {
            $pid = (int) trim((string) file_get_contents($pidFile));
            if ($pid > 0 && $this->ffmpegAlive($pid, $channelId)) {
                return;
            }
        }

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $cmd = sprintf(
            'ffmpeg -re -i %s -c:v copy -c:a copy -f hls '
            . '-hls_time 6 -hls_list_size 10 -hls_flags delete_segments '
            . '-hls_segment_filename %s/segment_%%03d.ts '
            . '%s/playlist.m3u8 > /dev/null 2>&1 & echo $!',
            escapeshellarg($sourceUrl),
            escapeshellarg($outputDir),
            escapeshellarg($outputDir)
        );

        $pid = trim((string) shell_exec($cmd));

        if ($pid !== '') {
            file_put_contents($pidFile, $pid);
            cache()->put("ffmpeg:channel:{$channelId}", $pid, 86400);
        }
    }

    private function ffmpegAlive(int $pid, int $channelId): bool
    {
        if (! posix_kill($pid, 0)) {
            return false;
        }

        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");

        if ($cmdline === false || $cmdline === '') {
            return false;
        }

        return str_contains($cmdline, 'ffmpeg')
            && str_contains($cmdline, "streams/hls/{$channelId}/playlist.m3u8");
    }

    // Stream a VOD
    public function streamVod(Request $request, $username, $password, $streamId)
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $vodId = (int) $streamId;
        $vod = VODContent::where('id', $vodId)->where('is_active', true)->firstOrFail();
        $media = $vod->vodMedia()->first();
        if (! $media?->stream_url) abort(404);

        return redirect($media->stream_url);
    }

    // Stream a series episode (streamId is vod_media.id)
    public function streamSeries(Request $request, $username, $password, $streamId)
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $episodeId = (int) $streamId;
        $media = VODMedia::where('id', $episodeId)->where('is_available', true)->firstOrFail();
        if (! $media->stream_url) abort(404);

        return redirect($media->stream_url);
    }

    // M3U playlist
    public function m3u(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response('Unauthorized', 401);

        $base = rtrim(config('app.url'), '/');
        $u = $request->username;
        // Use the user's m3u_token in stream URLs instead of the raw password
        // Auto-generate a token if the user doesn't have one
        if (! $user->m3u_token) {
            $user->update(['m3u_token' => \Str::random(32)]);
        }
        $token = $user->m3u_token;

        $lines = ['#EXTM3U'];

        $channels = Channel::with('categories')->where('is_active', true)->orderBy('channel_number')->get();
        foreach ($channels as $ch) {
            $lines[] = sprintf(
                '#EXTINF:-1 tvg-id="%s" tvg-name="%s" tvg-logo="%s" group-title="%s",%s',
                $ch->epg_channel_id ?? '',
                $ch->name,
                $ch->logo_url ?? '',
                $ch->categories->first()?->name ?? 'Uncategorized',
                $ch->name
            );
            $lines[] = "{$base}/live/{$u}/{$token}/{$ch->id}.m3u8";
        }

        $vods = VODContent::with(['categories', 'vodMedia'])->where('is_active', true)->where('type', 'movie')->get();
        foreach ($vods as $v) {
            $media = $v->vodMedia->first();
            $ext = $media?->stream_type ?? 'mp4';
            $lines[] = sprintf(
                '#EXTINF:-1 tvg-name="%s" tvg-logo="%s" group-title="%s",%s',
                $v->title,
                $v->poster_url ?? '',
                $v->categories->first()?->name ?? 'Movies',
                $v->title
            );
            $lines[] = "{$base}/movie/{$u}/{$token}/{$v->id}.{$ext}";
        }

        $series = VODContent::with(['categories', 'vodMedia'])->where('is_active', true)->where('type', 'series')->get();
        foreach ($series as $s) {
            $group = $s->categories->first()?->name ?? 'Series';
            foreach ($s->vodMedia as $ep) {
                $season = $ep->season_number ?? 1;
                $episode = $ep->episode_number ?? 1;
                $epTitle = sprintf('%s S%02dE%02d - %s', $s->title, $season, $episode, $ep->episode_title ?? $s->title);
                $ext = $ep->stream_url ? pathinfo($ep->stream_url, PATHINFO_EXTENSION) : 'mp4';
                $lines[] = sprintf(
                    '#EXTINF:-1 tvg-name="%s" tvg-logo="%s" group-title="%s" season="%d" episode="%d",%s',
                    $epTitle,
                    $s->poster_url ?? '',
                    $group,
                    $season,
                    $episode,
                    $epTitle
                );
                $lines[] = "{$base}/series/{$u}/{$token}/{$ep->id}.{$ext}";
            }
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/x-mpegurl',
            'Content-Disposition' => 'attachment; filename="playlist.m3u"',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function authenticate(Request $request): ?User
    {
        $username = $request->username ?? $request->input('username');
        $password = $request->password ?? $request->input('password');

        if (! $username || ! $password) return null;

        $user = User::where('username', $username)->first();

        if (! $user || ! $user->is_active) {
            return null;
        }

        // Accept either the password hash or the m3u_token
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    private function userInfo(User $user): array
    {
        $sub = $user->activeSubscription();

        return [
            'username'           => $user->username,
            'password'           => '',
            'message'            => '',
            'auth'               => 1,
            'status'             => 'Active',
            'exp_date'           => $sub ? (string) $sub->end_date?->timestamp : null,
            'is_trial'           => '0',
            'active_cons'        => '0',
            'created_at'         => (string) $user->created_at?->timestamp,
            'max_connections'    => (string) ($user->max_connections ?? 1),
            'allowed_output_formats' => ['m3u8', 'ts', 'rtmp'],
        ];
    }

    private function serverInfo(Request $request): array
    {
        $url = parse_url(config('app.url'));

        return [
            'url'           => $url['host'] ?? $request->getHost(),
            'port'          => (string) ($url['port'] ?? 80),
            'https_port'    => '443',
            'server_protocol' => $url['scheme'] ?? 'http',
            'rtmp_port'     => '1935',
            'timezone'      => config('app.timezone', 'UTC'),
            'timestamp_now' => time(),
            'time_now'      => now()->format('Y-m-d H:i:s'),
            'process'       => true,
        ];
    }
}
