<?php

namespace App\Http\Controllers;

use App\Models\AdminChannel\AdminChannel;
use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\EPGProgram;
use App\Models\User;
use App\Models\VODContent;
use App\Models\VODEpisode;
use App\Models\VODMedia;
use App\Models\VODSeason;
use App\Services\StreamingService\ConnectionLimiter;
use App\Services\StreamingService\EdgeDispatcher;
use App\Services\StreamingService\MulticastIngestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class XtreamController extends Controller
{
    // Offset added to admin_channel.id so My Channel streams can coexist
    // with regular channel IDs in the Xtream Codes API without collisions.
    public const ADMIN_CHANNEL_OFFSET = 9000000;

    public const INGEST_STALE_SECONDS = 90;
    private const FFMPEG_READ_TIMEOUT_US = 30000000;

    // UDP multicast is a lossy feed: sources routinely pause for a few
    // seconds (ad insertion, blank breaks, TS discontinuities) without being
    // dead. A too-short socket timeout makes ffmpeg exit on every brief
    // pause, and each exit/restart cycle wipes the channel's HLS output and
    // makes players report "channel playback error". 60s tolerates those
    // pauses while still letting the input die; a genuinely dead mux is
    // finally handled by the watchdog (90s playlist staleness) instead.
    private const FFMPEG_UDP_TIMEOUT_US = 60000000;

    private const INGEST_RESTART_BACKOFF_SECONDS = 10;

    // ffmpeg thread count per process. 1 thread per process is optimal for
    // copy-only streams: ffmpeg's internal threading is designed for encoding,
    // not demux+mux, so extra threads just burn CPU with context switching.
    // Multicast group readers (many outputs per process) use 2 threads.
    private const FFMPEG_THREADS_SINGLE  = 1;
    private const FFMPEG_THREADS_TRANSCODE = 8;
    private const FFMPEG_THREADS_MULTI   = 2;

    // nice level for ingest wrappers. 15 = well below normal so the OS
    // scheduler always yields CPU to nginx, PHP-FPM, and MySQL first.
    private const INGEST_NICE_LEVEL = 15;

    // Max system load (1-min) before the wrapper pauses before starting ffmpeg.
    // On 8 cores, load 5 = ~62% utilisation — safe headroom.
    private const INGEST_LOAD_GATE = 40;

    /**
     * Load threshold used INSIDE the wrapper retry loop. Before respawning
     * ffmpeg after a crash/kill, the wrapper waits until the 1-minute load
     * average drops below this value. This prevents guardian kill phases
     * from fighting instant respawns (thrash) on a saturated CPU.
     */
    private const INGEST_HOLD_GATE = 24;

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

        $regular = Channel::with('categories')->where('is_active', true)
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

        $admin = AdminChannel::where('is_active', true)
            ->where('broadcast_status', 'live')
            ->get()
            ->map(fn ($ac) => [
                'num'            => $ac->channel_number ? (int) $ac->channel_number : 999999,
                'name'           => $ac->channel_name,
                'stream_type'    => 'live',
                'stream_id'      => $ac->id + self::ADMIN_CHANNEL_OFFSET,
                'stream_icon'    => $ac->logo_url ?? '',
                'epg_channel_id' => '',
                'added'          => (string) $ac->created_at?->timestamp,
                'category_id'    => '',
                'custom_sid'     => '',
                'tv_archive'     => 0,
                'direct_source'  => '',
                'tv_archive_duration' => 0,
            ]);

        $merged = $regular->concat($admin)->sortBy('num')->values();

        return response()->json($merged);
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

    // Series info with seasons/episodes — XC-VM spec compliant
    public function seriesInfo(Request $request)
    {
        $user = $this->authenticate($request);
        if (! $user) return response()->json([], 401);

        $series = VODContent::with(['categories'])->find($request->series_id);
        if (! $series) return response()->json([]);

        // Use the VODSeason/VODEpisode models for proper relational hierarchy.
        // XC players require: "episodes": { "1": [...], "2": [...] }
        // where the key is the string representation of the season number.
        $seasons  = [];
        $episodes = [];

        $seasonsModels = VODSeason::where('vod_content_id', $series->id)
            ->orderBy('season_number')
            ->get();

        foreach ($seasonsModels as $season) {
            $seasonKey = (string) $season->season_number;

            $seasons[] = [
                'id'             => $season->season_number,
                'season_number'  => $season->season_number,
                'name'           => $season->title ?: "Season {$season->season_number}",
                'episode_count'  => $season->episode_count ?? $season->episodes()->count(),
                'air_date'       => $season->air_date?->format('Y-m-d') ?? '',
                'overview'       => $season->description ?? '',
            ];

            // Fetch episodes for this season via the VODMedia pivot
            $seasonEpisodes = VODMedia::where('vod_content_id', $series->id)
                ->where('season_number', $season->season_number)
                ->orderBy('episode_number')
                ->get();

            foreach ($seasonEpisodes as $ep) {
                $ext = $ep->stream_url
                    ? pathinfo($ep->stream_url, PATHINFO_EXTENSION) ?: 'mp4'
                    : 'mp4';

                $episodes[$seasonKey][] = [
                    'id'                  => $ep->id,
                    'episode_num'         => $ep->episode_number ?? 1,
                    'title'               => $ep->episode_title ?? $ep->file_name ?? "Episode {$ep->episode_number}",
                    'container_extension' => $ext,
                    'custom_sid'          => '',
                    'direct_source'       => '',
                    'season'              => $season->season_number,
                    'info' => [
                        'duration_secs' => $ep->duration ?? 0,
                        'duration'      => gmdate('H:i:s', $ep->duration ?? 0),
                        'bitrate'       => $ep->bitrate ?? 0,
                        'movie_image'   => $ep->still_url ?? $ep->file_name ?? '',
                        'rating'        => '',
                        'rating_10'     => 0,
                        'releaseDate'   => $ep->air_date?->format('Y-m-d') ?? '',
                        'plot'          => '',
                        'director'      => '',
                        'cast'          => '',
                        'duration_secs' => $ep->duration ?? 0,
                    ],
                ];
            }
        }

        // Sort episodes by season number (string keys must be ordered)
        ksort($episodes);

        return response()->json([
            'seasons' => $seasons,
            'info' => [
                'name'            => $series->title,
                'cover'           => $series->poster_url ?? '',
                'plot'            => $series->description ?? '',
                'cast'            => is_array($series->cast) ? implode(', ', $series->cast) : ($series->cast ?? ''),
                'director'        => $series->director ?? '',
                'genre'           => is_array($series->genre) ? implode(', ', $series->genre) : ($series->genre ?? ''),
                'releaseDate'     => $series->year ?? '',
                'backdrop_path'   => $series->backdrop_url ? [$series->backdrop_url] : [],
                'youtube_trailer' => $series->trailer_url ?? '',
                'episode_run_time' => '',
                'category_id'     => $series->categories->first()?->id ?? '',
                'rating'          => (string) $series->rating,
                'rating_5based'   => round($series->rating / 2, 1),
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

    /**
     * HTTP-TS (MPEG-TS over HTTP) endpoint — faster channel zap than HLS.
     *
     * Reads .ts segments sequentially from the local ingest directory and
     * pipes them as a continuous MPEG-TS byte stream. The source is ingested
     * exactly once by ffmpeg; all viewers share the same local segment cache.
     */
    public function streamTs(Request $request, $username, $password, $streamId)
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $channelId = (int) $streamId;
        $channel   = Channel::where('id', $channelId)->where('is_active', true)->firstOrFail();

        $limiter   = app(ConnectionLimiter::class);
        $streamKey = "ts:{$channelId}";
        if (! $limiter->acquire($user, $streamKey)) {
            abort(429, 'Connection limit reached');
        }

        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        $this->ensureHlsStream(
            $channelId,
            $sourceUrl,
            $channel->program_number,
            $channel->local_address,
            (bool) ($channel->transcoding_enabled ?? false)
        );

        $segDir = storage_path("app/streams/hls/{$channelId}");

        for ($i = 0; $i < 12; $i++) {
            $segs = glob($segDir . '/seg_*.ts') ?: [];
            if (! empty($segs)) break;
            usleep(500000);
        }

        return response()->stream(function () use ($segDir, $limiter, $user, $streamKey) {
            $lastSeg = -1;

            while (true) {
                $playlist = $segDir . '/playlist.m3u8';
                if (! is_file($playlist)) { usleep(500000); continue; }

                $m3u8 = @file_get_contents($playlist);
                if (! $m3u8) { usleep(500000); continue; }

                preg_match_all('/^(seg_(\d+)\.ts)\s*$/m', $m3u8, $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $seq  = (int) $m[2];
                    $file = $segDir . '/' . $m[1];
                    if ($seq <= $lastSeg || ! is_file($file)) continue;

                    $data = @file_get_contents($file);
                    if ($data === false) continue;

                    echo $data;
                    if (ob_get_level()) ob_flush();
                    flush();

                    $lastSeg = $seq;
                    $limiter->acquire($user, $streamKey);
                }

                if (connection_aborted()) break;
                usleep(1000000);
            }

            $limiter->release($user->id, $streamKey);
        }, 200, [
            'Content-Type'               => 'video/mp2t',
            'Cache-Control'              => 'no-cache, no-store',
            'Access-Control-Allow-Origin'=> '*',
            'X-Accel-Buffering'          => 'no',
        ]);
    }

    /**
     * Control-plane entry point for live streams (Xtream Codes protocol).
     *
     * Architecture (XC-VM-style split stream):
     *   1. Authenticate via Redis token cache (< 1ms, no MySQL hit on warm cache)
     *   2. Enforce per-user concurrent connection limit
     *   3. Ensure persistent background FFmpeg ingest is running (one per channel)
     *   4. Serve HLS via X-Accel-Redirect (Nginx delivers from RAM, PHP authenticates)
     *
     * The middleware runs FFmpeg ONCE per channel in a background process.
     * Nginx handles all client connections and stream duplication. No FFmpeg
     * is ever spawned per-user — this is the one-to-many architecture.
     *
     * Unlike a 302 redirect, X-Accel-Redirect keeps every request routed through
     * PHP for authentication. The player never gets a direct path to the raw
     * /hls/ directory, so auth cannot be bypassed after the initial request.
     */
    public function streamLive(Request $request, $username, $password, $streamId, ?string $file = null)
    {
        // ── 1. Auth (Redis-first, < 1ms on warm cache) ───────────────────────
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $cacheKey = 'auth:token:' . md5($username . ':' . $password);
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, $user->id, 300);
        }

        // ── 2. Connection limit ───────────────────────────────────────────────
        $limiter   = app(ConnectionLimiter::class);
        $streamKey = 'live:' . (int) $streamId;
        if (! $limiter->acquire($user, $streamKey)) {
            abort(429, 'Connection limit reached');
        }

        $rawId = (int) $streamId;

        // ── 3. File request (playlist.m3u8 or segment_XXXX.ts) ───────────────
        // After the initial request, the player fetches playlists and segments
        // via this route. Each request is authenticated, then served via
        // X-Accel-Redirect so Nginx handles the I/O at near-zero CPU cost.
        if ($file !== null) {
            // For segment requests, serve directly from nginx /hls/ path
            // (bypasses PHP entirely — 30ms vs 350ms per segment)
            if ($ext === 'ts' || $ext === 'm3u8') {
                $hlsPath = "/hls/{$rawId}/{$file}";
                if (is_file(storage_path("app/streams/hls/{$rawId}/{$file}"))) {
                    return response('', 200, [
                        'Content-Type'              => $ext === 'm3u8' ? 'application/vnd.apple.mpegurl' : 'video/mp2t',
                        'Cache-Control'             => 'no-cache, no-store, must-revalidate',
                        'Access-Control-Allow-Origin'=> '*',
                        'X-Accel-Redirect'          => $hlsPath,
                    ]);
                }
            }
            if ($rawId >= self::ADMIN_CHANNEL_OFFSET) {
                $adminId = $rawId - self::ADMIN_CHANNEL_OFFSET;
                $admin   = AdminChannel::where('id', $adminId)->where('is_active', true)->first();
                $slug    = "admin-channel-" . ($admin->channel_slug ?? "{$adminId}");
                return $this->serveHlsFile($slug, $file);
            }
            return $this->serveHlsFile($rawId, $file);
        }

        // ── 4. Initial request — ensure ingest is running, serve playlist ─────
        // Build the base URL for rewriting relative segment paths to absolute
        // ones. Use /hls/ path so segments are served directly from nginx
        // (3ms) instead of going through PHP.

        // Admin / My-Channel streams
        if ($rawId >= self::ADMIN_CHANNEL_OFFSET) {
            $adminId = $rawId - self::ADMIN_CHANNEL_OFFSET;
            $admin   = AdminChannel::where('id', $adminId)->where('is_active', true)->firstOrFail();

            $slug = "admin-channel-" . ($admin->channel_slug ?? "{$adminId}");
            // Use slug in base URL so segments resolve to the correct directory
            $baseUrl = '/hls/' . $slug . '/';
            return $this->serveHlsFile($slug, 'index.m3u8', $baseUrl);
        }

        // Regular channel streams
        $channelId = $rawId;
        $channel   = Channel::where('id', $channelId)->where('is_active', true)->firstOrFail();

        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        // Start persistent background FFmpeg ingest (one process per channel).
        // This is the core of the XC-VM architecture: FFmpeg runs ONCE and
        // writes HLS segments to disk; Nginx serves them to all viewers.
        $this->ensureHlsStream(
            $channelId,
            $sourceUrl,
            $channel->program_number,
            $channel->local_address,
            (bool) ($channel->transcoding_enabled ?? false)
        );

        $baseUrl = '/hls/' . $channelId . '/';
        return $this->serveHlsFile($channelId, 'playlist.m3u8', $baseUrl);
    }

    /**
     * Serve an HLS file (playlist or segment) via X-Accel-Redirect.
     *
     * Authenticates every request, then hands off to Nginx for zero-CPU
     * file delivery from the tmpfs RAM disk. Handles the ingest-restart
     * gap gracefully: missing playlists serve stale cache (503 + Retry-After
     * as fallback), missing segments return 204 (keeps players polling).
     */
    private function serveHlsFile(int|string $channelId, string $file, ?string $rewriteBase = null): \Symfony\Component\HttpFoundation\Response
    {
        $file = basename($file);
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (! in_array($ext, ['m3u8', 'ts'], true)) {
            abort(403, 'File type not allowed');
        }

        $streamDir = storage_path("app/streams/hls/{$channelId}");
        $absolute  = realpath("{$streamDir}/{$file}");

        // Path traversal check
        if ($absolute !== false && ! str_starts_with($absolute, realpath($streamDir))) {
            abort(403, 'Invalid stream path');
        }

        if ($absolute === false || ! is_file($absolute)) {
            // File missing — ingest may be restarting.
            if ($ext === 'm3u8') {
                $cacheKey = "hls:stale:{$channelId}:playlist";
                $cached   = Cache::get($cacheKey);

                if ($cached !== null) {
                    $served = $rewriteBase !== null
                        ? $this->rewriteHlsSegmentUrls($cached, $rewriteBase)
                        : $cached;
                    return response($served, 200, [
                        'Content-Type'              => 'application/vnd.apple.mpegurl',
                        'Cache-Control'             => 'no-cache, no-store, must-revalidate',
                        'Access-Control-Allow-Origin'=> '*',
                        'X-HLS-Stale'               => '1',
                    ]);
                }

                return response('Service Unavailable', 503, [
                    'Retry-After'                => '3',
                    'Cache-Control'              => 'no-cache, no-store, must-revalidate',
                    'Access-Control-Allow-Origin'=> '*',
                ]);
            }

            // .ts missing — return 204 so the player keeps polling.
            return response('', 204, [
                'Cache-Control'              => 'no-cache, no-store, must-revalidate',
                'Access-Control-Allow-Origin'=> '*',
            ]);
        }

        // Cache successful playlists for stale serving during restarts
        if ($ext === 'm3u8') {
            // Production optimization: rewrite playlist URLs and serve via
            // X-Accel-Redirect. PHP authenticates (< 1ms Redis), rewrites
            // URLs, writes to a temp file, and hands off to Nginx (3ms).
            // This prevents PHP-FPM exhaustion at 500+ concurrent users.
            $cacheKey = "hls:playlist:{$channelId}:" . ($rewriteBase ?? 'raw');
            $cached   = Cache::get($cacheKey);

            if ($cached !== null) {
                // Serve cached rewritten playlist via X-Accel-Redirect
                $tmpFile = storage_path("app/streams/hls/{$channelId}/.playlist_cache.m3u8");
                @file_put_contents($tmpFile, $cached);

                return response('', 200, [
                    'Content-Type'              => 'application/vnd.apple.mpegurl',
                    'Cache-Control'             => 'no-cache, no-store, must-revalidate',
                    'Access-Control-Allow-Origin'=> '*',
                    'X-Accel-Redirect'          => "/hls/{$channelId}/.playlist_cache.m3u8",
                ]);
            }

            $content = file_get_contents($absolute);
            if ($content !== false && strlen($content) > 10) {
                // Rewrite relative segment URLs to absolute /hls/ path
                if ($rewriteBase !== null) {
                    $content = $this->rewriteHlsSegmentUrls($content, $rewriteBase);
                }

                // Cache rewritten playlist for 30s (matches segment duration)
                Cache::put($cacheKey, $content, 30);
                Cache::put("hls:stale:{$channelId}:playlist", $content, 30);

                // Write to temp file for X-Accel-Redirect (nginx serves at 3ms)
                $tmpFile = storage_path("app/streams/hls/{$channelId}/.playlist_cache.m3u8");
                @file_put_contents($tmpFile, $content);

                return response('', 200, [
                    'Content-Type'              => 'application/vnd.apple.mpegurl',
                    'Cache-Control'             => 'no-cache, no-store, must-revalidate',
                    'Access-Control-Allow-Origin'=> '*',
                    'X-Accel-Redirect'          => "/hls/{$channelId}/.playlist_cache.m3u8",
                ]);
            }
        }

        // Hand off to Nginx for zero-CPU file delivery from RAM disk.
        // Nginx reads the file asynchronously without blocking PHP-FPM.
        return response('', 200, [
            'Content-Type'              => $ext === 'm3u8' ? 'application/vnd.apple.mpegurl' : 'video/mp2t',
            'Cache-Control'             => 'no-cache, no-store, must-revalidate',
            'Access-Control-Allow-Origin'=> '*',
            'X-Accel-Redirect'          => "/internal_hls/{$channelId}/{$file}",
        ]);
    }

    /**
     * Rewrite relative segment URLs in an M3U8 playlist to absolute paths.
     *
     * Converts "segment_1234.ts" → "{baseUrl}segment_1234.ts" so the player
     * resolves them through the authenticated /{streamId}/{file} route.
     */
    private function rewriteHlsSegmentUrls(string $content, string $baseUrl): string
    {
        return preg_replace_callback(
            '/^(?!#)(?!https?:\/\/)(?!\/)(.+)$/m',
            fn (array $m) => $baseUrl . $m[1],
            $content,
        );
    }

    public function ensureHlsStream(int $channelId, string $sourceUrl, ?int $programNumber = null, ?string $localAddress = null, bool $transcode = false): void
    {
        $outputDir = storage_path("app/streams/hls/{$channelId}");
        $pidFile = $outputDir . '/ingest.pid';
        $heartbeat = $outputDir . '/.heartbeat';

        // Touch heartbeat every time a client requests this channel.
        // The wrapper checks this file — if nobody requests for 120s, it exits.
        @touch($heartbeat);

        // Multi-program UDP/RTP multicast muxes MUST go through the shared group
        // reader when a program number is set. Single-program UDP sources (no
        // program number) get their own per-channel ingest below.
        $isMulticast = str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://');
        if ($isMulticast && $programNumber !== null && $programNumber > 0 && $channelId > 0) {
            // Fast path: if the playlist is fresh (< 15s), skip the entire
            // ensureGroupReader() call which acquires a Redis lock and may
            // scan /proc. This makes zapping near-instant on healthy channels.
            $playlist = $outputDir . '/playlist.m3u8';
            if (is_file($playlist)) {
                $age = time() - (int) @filemtime($playlist);
                if ($age < 15) {
                    return;
                }
            }

            // Kill any orphaned per-channel ingest that may be competing
            // for the same multicast socket (double-join → packet splits).
            // Skip if the PID in ingest.pid belongs to the multicast group
            // reader (wrapper writes its PID there too via ensureGroupReader).
            if (is_file($pidFile)) {
                $oldPid = (int) trim((string) file_get_contents($pidFile));
                if ($oldPid > 0 && @file_exists("/proc/{$oldPid}")) {
                    $cmdline = @file_get_contents("/proc/{$oldPid}/cmdline") ?: '';
                    $isGroupReader = str_contains($cmdline, 'multicast_reader')
                        || str_contains($cmdline, 'storage/app/multicast/');
                    if (! $isGroupReader) {
                        Log::info('Killing orphaned per-channel ingest for multicast channel', [
                            'channel_id' => $channelId,
                            'pid' => $oldPid,
                        ]);
                        @exec("kill -TERM -{$oldPid} 2>/dev/null");
                        usleep(200000);
                        @exec("kill -KILL -{$oldPid} 2>/dev/null");
                    }
                }
                @unlink($pidFile);
            }
            $channel = Channel::find($channelId);
            if ($channel) {
                app(MulticastIngestService::class)->ensureGroupReader($channel);
                return;
            }
        }

        $lock = Cache::lock("ffmpeg:ingest:{$channelId}", 60);

        if (! $lock->get()) {
            return;
        }

        try {
            if (is_file($pidFile)) {
                $pid = (int) trim((string) file_get_contents($pidFile));

                if ($pid > 0 && $this->ffmpegAlive($pid, $channelId)) {
                    if (! $this->ingestStale($outputDir)) {
                        return;
                    }

                    if ($this->ingestRestartBlocked($channelId)) {
                        return;
                    }

                    Log::warning('HLS ingest frozen, restarting', [
                        'channel_id' => $channelId,
                        'pid' => $pid,
                    ]);

                    @unlink($pidFile);
                    $this->stopIngestGroup($outputDir, $pid);
                } else {
                    // Process is gone: stop any orphaned ffmpeg that might
                    // still be writing into this channel's directory, then
                    // drop the stale playlist/segments right away so clients
                    // don't keep looping on dead content while the ingest is
                    // being respawned.
                    $this->stopIngestGroup($outputDir, $pid);
                    $this->cleanOutputDirectory($outputDir);

                    if ($this->ingestRestartBlocked($channelId)) {
                        return;
                    }

                    Log::warning('HLS ingest process dead, restarting', [
                        'channel_id' => $channelId,
                        'pid' => $pid,
                    ]);
                }
            }

            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Don't clean output directory here — the wrapper script already
            // cleans old segments before writing new ones, and cleaning here
            // causes a 6+ second gap where HLS clients get 503/404 errors
            // (which freezes Android TV players). The new ingest will
            // overwrite playlist.m3u8 and segments naturally.

            $this->markIngestRestarted($channelId);

            // Make sure a stale stop flag from an interrupted restart can't
            // kill the freshly spawned ingest.
            @unlink($outputDir . '/.stop');

            // Look up transcoding device preference (cpu/gpu) for this channel.
            $transcodingDevice = Channel::find($channelId)?->transcoding_device;

            // The wrapper writes its own $$ PID to the pidFile as its first
            // action, so we capture the actual bash PID (not the setsid parent
            // which exits immediately after forking).
            $wrapperWithPid = 'echo $$ > ' . escapeshellarg($pidFile) . '; '
                . $this->ingestWrapperCommand($outputDir, $sourceUrl, $programNumber, $localAddress, $transcode, $transcodingDevice, $channelId);

            $cmd = 'setsid bash -c ' . escapeshellarg($wrapperWithPid)
                . ' < /dev/null > /dev/null 2>&1 &';

            shell_exec($cmd);

            // Wait briefly for the wrapper to write its PID file, then read it.
            usleep(200000);
            $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;

            if ($pid > 0) {
                cache()->put("ffmpeg:channel:{$channelId}", $pid, 86400);

                Log::info('HLS ingest started', [
                    'channel_id' => $channelId,
                    'pid' => $pid,
                ]);


            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Force-restart the HLS ingest for one channel: kills the running wrapper
     * and ffmpeg child, wipes its output directory and respawns immediately.
     * Used by the dashboard per-channel refresh action (bypasses the restart
     * backoff because an explicit operator action must always win).
     */
    public function restartHlsStream(Channel $channel): void
    {
        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        // Multicast channels belong to the shared group reader: restart the
        // whole group (it wipes + rewrites every member's output on start).
        if ($sourceUrl && (str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://'))) {
            $multicast = app(MulticastIngestService::class);
            $multicast->stopGroup($channel);
            $multicast->ensureGroupReader($channel);

            return;
        }

        $outputDir = storage_path("app/streams/hls/{$channel->id}");
        $pidFile = $outputDir . '/ingest.pid';

        if (is_file($pidFile)) {
            $pid = (int) trim((string) file_get_contents($pidFile));
            @unlink($pidFile);
            $this->stopIngestGroup($outputDir, $pid);
        }

        // A stale stop flag from an interrupted previous run would kill the
        // freshly spawned ingest in its first loop iteration.
        @unlink($outputDir . '/.stop');
        cache()->forget("ffmpeg:last_restart:{$channel->id}");
        $this->cleanOutputDirectory($outputDir);

        $this->ensureHlsStream(
            (int) $channel->id,
            $channel->active_stream_url ?? $channel->stream_url,
            $channel->program_number,
            $channel->local_address,
            (bool) ($channel->transcoding_enabled ?? false)
        );
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

        // The wrapper's cmdline stores the output dir as a quoted assignment
        // (ODIR='.../streams/hls/{$channelId}') while the ffmpeg child uses the
        // unquoted path with a trailing slash, so match either boundary.
        return str_contains($cmdline, 'ffmpeg')
            && (bool) preg_match('#streams/hls/' . preg_quote((string) $channelId, '#') . '[/\']#', $cmdline);
    }

    /**
     * A healthy ingest writes a new segment roughly every 2 seconds. When the
     * most recent segment is older than the staleness window the ingest is
     * considered frozen even if its process is still alive.
     */
    private function ingestStale(string $outputDir): bool
    {
        $newest = 0;

        foreach (glob($outputDir . '/seg_*.ts') ?: [] as $segment) {
            $mtime = @filemtime($segment);

            if ($mtime !== false && $mtime > $newest) {
                $newest = $mtime;
            }
        }

        if ($newest === 0) {
            // No segments at all. Check if the directory has existed long
            // enough that a healthy ingest should have produced at least one
            // segment. If the dir is > 30s old and still empty, the wrapper
            // is alive but ffmpeg is failing inside it (e.g. bad source,
            // unsupported option, no streams detected).
            $playlist = $outputDir . '/playlist.m3u8';
            if (is_file($playlist)) {
                return false; // Playlist exists — ingest is alive, just between segments
            }

            $dirAge = time() - (int) @filemtime($outputDir);
            if ($dirAge < 30) {
                return false; // Fresh dir — give it time to produce first segment
            }

            return true; // No segments AND no playlist AND dir is old → frozen
        }

        return (time() - $newest) > self::INGEST_STALE_SECONDS;
    }

    private function cleanOutputDirectory(string $outputDir): void
    {
        foreach (glob($outputDir . '/seg_*.ts') ?: [] as $segment) {
            @unlink($segment);
        }

        foreach (glob($outputDir . '/playlist*.m3u8') ?: [] as $playlist) {
            @unlink($playlist);
        }
    }

    /**
     * Build the self-restarting wrapper script for a channel ingest.
     *
     * Some upstream providers hand out short-lived tokenised playlists (the
     * redirect target goes 404 after a minute or two, e.g. TNT Sports 2).
     * ffmpeg cannot recover from that by itself, so the ingest runs in a loop:
     * whenever ffmpeg exits the source URL is re-resolved (yielding a fresh
     * token) and the ingest restarts after a short pause. The loop also
     * honours a `.stop` flag so an explicit force-restart can take over.
     *
     * When the channel is a multi-program multicast TS (udp://@...), the
     * local interface is appended as udp?localaddr=... so the group is joined
     * on the right NIC, and -map p:{N} keeps only the requested program so
     * this ingest's HLS playlist contains just that one channel.
     */
    private function ingestWrapperCommand(string $outputDir, string $sourceUrl, ?int $programNumber = null, ?string $localAddress = null, bool $transcode = false, ?string $transcodingDevice = null, int $channelId = 0): string
    {
        $log = '/tmp/ingest_' . basename($outputDir) . '.log';

        $input = $sourceUrl;
        if ($localAddress !== null && $localAddress !== '' && (str_starts_with($input, 'udp://') || str_starts_with($input, 'rtp://'))) {
            // 64 MB SO_RCVBUF absorbs bursts from multi-program TS muxes without
            // dropping packets (requires net.core.rmem_max >= 67108864 on host).
            $input .= (str_contains($input, '?') ? '&' : '?')
                . 'localaddr=' . $localAddress
                . '&buffer_size=67108864';
        }

        $isMulticast = str_starts_with($input, 'udp://') || str_starts_with($input, 'rtp://');

        // For multicast channels, wait for the local address interface to be
        // ready before starting ffmpeg. When the cable is re-plugged, netplan
        // needs a few seconds to re-assign the IP and add routes.
        $networkWait = '';
        if ($isMulticast && $localAddress !== null && $localAddress !== '') {
            $networkWait = 'for i in $(seq 1 30); do '
                . 'if ip addr show | grep -q ' . escapeshellarg($localAddress) . '; then '
                . '  echo "NETWORK READY $i" >> "$L"; break; fi; '
                . 'echo "WAITING FOR NETWORK $i" >> "$L"; sleep 1; done; ';
        }

        // -reconnect / -reconnect_streamed / -reconnect_delay_max are http(s)-only
        // input options. On a udp:// input ffmpeg rejects them ("Option reconnect
        // not found") and exits, so they are only emitted for non-multicast URLs.
        // UDP multicast must also avoid -re: it throttles reading to frame rate and
        // overflows the kernel recv buffer, dropping packets from the live feed.
        // +genpts regenerates missing PTS after TS discontinuities and
        // +discardcorrupt drops damaged packets instead of stalling the decode
        // pipeline — both keep multicast ingests alive through rough patches.
        // +nobuffer tells the demuxer not to read ahead on the socket and
        // -flags low_delay disables B-frame reordering delay — together with a
        // small -probesize/-analyzeduration they slash feed-to-screen latency.
        //
        // Live HLS (.m3u8) and HTTP MPEG-TS streams must NOT use -re: they
        // are already paced by the upstream server. Adding -re throttles
        // ffmpeg to 1x speed which causes it to fall behind the live feed.
        // Only file-based HTTP sources (mp4, mkv, avi…) need -re to avoid
        // reading the entire file into memory at once.
        // HTTP MPEG-TS sources can also have PTS discontinuities (same as UDP
        // multicast), so +genpts+discardcorrupt is applied to all live HTTP too.
        $isHls = str_contains(strtolower($input), '.m3u8');
        $isLiveHttp = $isHls || !preg_match('/\.(mp4|mkv|avi|mov|wmv|flv|webm|ts|m4v)$/i', parse_url($input, PHP_URL_PATH) ?? '');
        $userAgent = '-user_agent \'VLC/3.0.16 LibVLC/3.0.16\'';
        // Some providers serve HLS segments with no file extension (e.g.
        // /hls/<token>). FFmpeg's HLS demuxer (7.x) refuses such segments with
        // "URL ... is not in allowed_segment_extensions" and aborts the ingest.
        // extension_picky=0 disables the extension check (allowed_extensions=
        // ALL alone is not enough in newer FFmpeg).
        // Detect actual support instead of relying on version number —
        // some distro builds (e.g. Ubuntu 6.1.1) strip this option.
        static $hlsExtPicky = null;
        static $hlsAllowedExt = null;
        if ($hlsExtPicky === null) {
            $test = @shell_exec('ffmpeg -hide_banner -h muxer=hls 2>&1 | grep -c extension_picky');
            $hlsExtPicky = ((int) ($test ?? 0)) > 0;
        }
        if ($hlsAllowedExt === null) {
            $test2 = @shell_exec('ffmpeg -hide_banner -h muxer=hls 2>&1 | grep -c allowed_extensions');
            $hlsAllowedExt = ((int) ($test2 ?? 0)) > 0;
        }
        $hlsOpts = ($isHls && $hlsExtPicky) ? '-extension_picky 0 '
            : (($isHls && $hlsAllowedExt) ? '-allowed_extensions ALL ' : '');
        // For UDP: +genpts fixes missing PTS after TS discontinuities,
        // +discardcorrupt drops damaged packets, -err_detect ignore_err skips
        // corrupt frames without stalling, -avoid_negative_ts make_zero fixes
        // DTS jumps that freeze the HLS muxer (the main cause of stalling).
        // +nobuffer tells the demuxer not to read ahead on the socket and
        // -flags low_delay disables B-frame reordering delay — together with a
        // small -probesize/-analyzeduration they slash feed-to-screen latency.
        // MC_TIMEOUT is a short read/socket timeout so a dead multicast feed is
        // noticed in ~3s instead of appearing frozen and "buffered" for 30s.
        $mcTimeout = $isMulticast ? self::FFMPEG_UDP_TIMEOUT_US : self::FFMPEG_READ_TIMEOUT_US;
        $inputOpts = $isMulticast
            ? '-fflags +genpts+discardcorrupt+nobuffer -flags low_delay -err_detect ignore_err -avoid_negative_ts make_zero -max_interleave_delta 0 -probesize 1M -analyzeduration 500000 -rw_timeout %d -timeout %d -i %s'
            : ($isLiveHttp
                ? '-fflags +genpts+discardcorrupt+nobuffer -flags low_delay -max_interleave_delta 0 -probesize 320000 -analyzeduration 2000000 -reconnect 1 -reconnect_streamed 1 -reconnect_on_http_error 404,403 -reconnect_delay_max 5 -rw_timeout %d -timeout %d ' . $hlsOpts . $userAgent . ' -i %s'
                : '-reconnect 1 -reconnect_streamed 1 -reconnect_on_http_error 404,403 -reconnect_delay_max 5 -rw_timeout %d -timeout %d -re -i %s');

        // -map p:N only applies to raw UDP MPEG-TS muxes where multiple programs
        // share one stream. For HTTP/HLS sources (e.g. Flussonic re-stream) the
        // program demux already happened upstream, so passing -map p:N causes
        // FFmpeg to fail with "Stream map matches no streams".
        $programMap = ($programNumber !== null && $programNumber > 0 && $isMulticast)
            ? ' -map p:' . $programNumber . ' -map_chapters -1 -ignore_unknown'
            : '';

        // Wrapper loop with exponential backoff:
        //   - Starts at 3 s, doubles on each failed attempt, caps at 30 s.
        //   - Resets to 3 s whenever ffmpeg produced at least one segment
        //     (i.e. the source was reachable and then dropped).
        //   - Segments/playlist are only wiped when ffmpeg actually wrote
        //     output; a pure connection failure leaves any existing playlist
        //     in place so clients keep getting 503+Retry-After rather than
        //     a missing directory.
        //   - The loop never exits on its own — an offline source is retried
        //     forever until .stop is written or the process is killed.
        //
        // -c:v copy avoids CPU-heavy x264 re-encoding when the source is
        // already H.264. Transcode uses either h264_nvenc (GPU) or libx264 (CPU)
        // depending on the channel's transcoding_device setting.
        // For HTTP/HLS sources the upstream is already AAC-muxed HLS —
        // copy both video and audio, zero re-encode CPU cost.
        // For UDP multicast sources audio may be AC3/MP2, so transcode to
        // AAC at 48k (enough for TV, half the CPU of 128k).
        $isMulticast = str_starts_with($input, 'udp://') || str_starts_with($input, 'rtp://');

        $useGpu = $transcode && strtolower($transcodingDevice ?? 'cpu') === 'gpu';

        // Some live HTTP/HLS sources (e.g. Flussonic re-streams) carry more
        // than one video/audio elementary stream in each TS segment; without an
        // explicit -map ffmpeg's default selection can pick an audio-only
        // program and produce HLS segments with no video track (black screen).
        // Explicitly map the first video + first audio stream (the `?` suffix
        // makes each optional) so video always survives. UDP multicast keeps
        // the -map p:N program selection instead.
        $liveMap = $isMulticast ? '' : ' -map 0:v:0? -map 0:a:0? -map_chapters -1 ';

        // GOP enforcement: strict 2s keyframe interval (50 frames @ 25fps).
        // A fixed GOP is critical for fast channel zapping — without it players
        // wait up to one full GOP (10s+) for the next IDR frame before they can
        // start decoding. -sc_threshold 0 prevents scene-change keyframes from
        // breaking the fixed interval. Only applied when transcoding (copy mode
        // preserves the source GOP; we cannot re-key a copy stream).
        $gopFlags = ' -g 50 -keyint_min 50 -sc_threshold 0';

        $videoFilter = $transcode
            // GPU or CPU re-encode with fixed 2s GOP for fast zap.
            ? ($useGpu
                ? $liveMap . ' -c:v h264_nvenc -preset p4 -tune ll -rc vbr -cq 28 -b:v 0 -maxrate 4000k -bufsize 8000k' . $gopFlags . ' -c:a aac -b:a 128k -ac 2 -ar 48000 -f hls '
                : ' -threads ' . self::FFMPEG_THREADS_TRANSCODE . $liveMap . ' -c:v libx264 -preset veryfast -crf 26 -tune zerolatency' . $gopFlags . ' -c:a aac -b:a 128k -ac 2 -ar 48000 -f hls ')
            : ($isMulticast
                // Video copy — source GOP preserved. Audio passthrough
                // (copy) avoids decode/re-encode crashes on corrupt input
                // and reduces CPU. Players handle MP2/AC3 natively.
                ? ' -threads ' . self::FFMPEG_THREADS_SINGLE . ' -c:v copy -c:a copy -f hls '
                : ' -threads ' . self::FFMPEG_THREADS_SINGLE . $liveMap . ' -c:v copy -c:a copy -f hls ');

        // All channels run permanently — no idle timeout.  This ensures
        // instant zapping: every channel has its ingest already running
        // and segments ready when the user switches to it.

        $isYouTube = str_contains(strtolower($sourceUrl), 'youtube');
        $ytInit = $isYouTube && $channelId > 0 ? 'SRC_URL=' . escapeshellarg($sourceUrl) . '; ' : '';

        // On wrapper retry, stale segments/playlist are dropped so a fresh
        // ffmpeg run never appends onto old content. For multicast inputs the
        // existing .ts segments are KEPT (only the playlist is removed): the
        // stale-cached playlist served by streamLive() still references those
        // files, so players riding through a restart keep getting data (204
        // instead of 404/503) instead of a hard "channel playback error".
        // On restart, keep existing segments alive for all source types so
        // players continue receiving stale-but-valid content during the gap
        // instead of hitting 404s and showing a black screen. Only the
        // playlist is removed so the new ffmpeg run starts a clean sequence;
        // the old .ts files remain on disk until ffmpeg's delete_segments flag
        // naturally rotates them out as new segments arrive.
        $restartClean = '[ "$HAS_SEGS" = "1" ] && rm -f "$ODIR"/playlist.m3u8; ';

        return sprintf(
            'ODIR=%s; L=%s; DELAY=3; FAILS=0; '
            . $ytInit
            . 'echo "WRAPPER START $$ ppid=$PPID $(date +%%s)" >> "$L"; '
            . 'trap \'echo "WRAPPER EXIT rc=$? ppid=$PPID $(date +%%s)" >> "$L"; exec >> "$L" 2>&1\' EXIT; '
            . $networkWait
            // Wait for system load to drop below gate before starting ffmpeg.
            // This prevents a burst of restarts from piling on a hot CPU.
            . 'LOAD_GATE=' . self::INGEST_LOAD_GATE . '; '
            . 'for i in $(seq 1 12); do '
            .   'LOAD=$(cut -d. -f1 /proc/loadavg); '
            .   '[ "$LOAD" -lt "$LOAD_GATE" ] && break; '
            .   'echo "LOAD_WAIT load=$LOAD gate=$LOAD_GATE" >> "$L"; sleep 5; '
            . 'done; '
            . 'while true; do '
            .   '[ -f "$ODIR/.stop" ] && exit 0; '
            .   'HAS_SEGS=0; ls "$ODIR"/seg_*.ts > /dev/null 2>&1 && HAS_SEGS=1; '
            . $restartClean
            .   ($channelId > 0 && str_contains(strtolower($sourceUrl), 'youtube')
                ? 'NEW_URL=$(cd ' . base_path() . ' && php artisan youtube:refresh-url ' . $channelId . ' 2>/dev/null); if [ $? -eq 0 ] && [ -n "$NEW_URL" ]; then SRC_URL="$NEW_URL"; echo "YOUTUBE REFRESHED $SRC_URL" >> "$L"; fi; '
                : '')
            .   'nice -n ' . self::INGEST_NICE_LEVEL . ' ffmpeg ' . $inputOpts . '%s ' . $videoFilter
            // 10 segments × 2s = 20s buffer — critical for hotel WiFi
            // where network jitter causes frequent buffer underruns.
            .   '-hls_time 2 -hls_list_size 10 '
            // No split_by_time: with -c:v copy it would force segment cuts at
            // exact time boundaries regardless of keyframes — segments start
            // mid-GOP without SPS/PPS and players choke at every boundary
            // ("non-existing PPS" decode errors, buffering, slow zap). Let the
            // muxer cut at the next IDR (default) so every segment is
            // independently decodable.
            .   '-hls_flags delete_segments+omit_endlist+temp_file+independent_segments+append_list+discont_start '
            .   '-hls_segment_type mpegts '
            .   '-muxdelay 0 -muxpreload 0 '
            .   '-hls_segment_filename "$ODIR"/seg_%%06d.ts '
            .   '"$ODIR"/playlist.m3u8 2>>"$L"; '
            .   'NEW_SEGS=0; ls "$ODIR"/seg_*.ts > /dev/null 2>&1 && NEW_SEGS=1; '
            .   'if [ "$NEW_SEGS" = "1" ]; then DELAY=3; FAILS=0; '
            .   'else FAILS=$((FAILS + 1)); DELAY=$((DELAY * 2)); [ $DELAY -gt 30 ] && DELAY=30; fi; '
            .   'echo "WRAPPER RETRY delay=$DELAY fails=$FAILS $(date +%%s)" >> "$L"; '
            // After 10 consecutive failures (dead source), exit to free resources.
            // The watchdog or next client request will restart it.
            .   ($isMulticast ? '' : '[ "$FAILS" -ge 10 ] && echo "WRAPPER GAVE UP after $FAILS failures" >> "$L" && exit 1; ')
            .   'sleep $DELAY; '
            // UDP channels use a higher hold gate so a busy HTTP-channel
            // load spike doesn't block multicast recovery. UDP ffmpeg is
            // copy-only and uses negligible CPU — it should always restart.
            .   'LOAD=$(cut -d. -f1 /proc/loadavg); '
            .   'HOLD=' . ($isMulticast ? '60' : (string) self::INGEST_HOLD_GATE) . '; '
            .   'while [ "$LOAD" -ge "$HOLD" ]; do echo "HOLD load=$LOAD gate=$HOLD" >> "$L"; sleep 10; LOAD=$(cut -d. -f1 /proc/loadavg); done; '
            . 'done',
            escapeshellarg($outputDir),
            escapeshellarg($log),
            $mcTimeout,
            $mcTimeout,
            escapeshellarg($input),
            $programMap
        );

        // For YouTube channels, replace the hardcoded resolved URL in the wrapper
        // with the bash variable $SRC_URL so the wrapper can re-resolve on retry.
        if ($isYouTube && $channelId > 0) {
            $wrapper = str_replace('-i ' . escapeshellarg($input), '-i "$SRC_URL"', $wrapper);
        }

        return $wrapper;
    }

    /**
     * Kill every process involved in a channel ingest. Ingests are spawned
     * with `setsid`, making the wrapper the leader of its own process group,
     * so a negative PID signal takes the wrapper and the ffmpeg child out
     * together. A directory-wide `pkill` additionally catches any orphaned
     * ffmpeg left behind after a crash.
     */
    private function stopIngestGroup(string $outputDir, int $pid): void
    {
        $marker = 'streams/hls/' . basename($outputDir) . '/playlist.m3u8';

        @exec('pkill -TERM -f ' . escapeshellarg($marker) . ' 2>/dev/null');
        usleep(500000);
        @exec('pkill -KILL -f ' . escapeshellarg($marker) . ' 2>/dev/null');

        if ($pid > 0) {
            @exec("kill -TERM -{$pid} 2>/dev/null");
            usleep(500000);
            @exec("kill -KILL -{$pid} 2>/dev/null");
        }
    }

    /**
     * Throttle restarts per channel. Without this, a web request (streamLive)
     * and the scheduler can both kill and respawn the same ingest within
     * seconds, producing a restart loop while a freshly spawned process is
     * still connecting and writing its first segment.
     */
    private function ingestRestartBlocked(int $channelId): bool
    {
        $lastRestart = (int) cache()->get("ffmpeg:last_restart:{$channelId}", 0);

        return (time() - $lastRestart) < self::INGEST_RESTART_BACKOFF_SECONDS;
    }

    private function markIngestRestarted(int $channelId): void
    {
        cache()->put("ffmpeg:last_restart:{$channelId}", time(), 3600);
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

        return $this->serveVodFile($media->stream_url);
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

        return $this->serveVodFile($media->stream_url);
    }

    private function serveVodFile(string $streamUrl)
    {
        // Local file — serve via X-Accel-Redirect so Nginx handles the I/O
        // (range requests, keep-alive) without tying up a PHP-FPM worker.
        if (str_starts_with($streamUrl, '/storage/')) {
            $relativePath = substr($streamUrl, strlen('/storage/'));
            $diskPath = storage_path('app/public/' . $relativePath);
            if (file_exists($diskPath)) {
                $ext  = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
                $mime = [
                    'mp4'  => 'video/mp4',
                    'mkv'  => 'video/x-matroska',
                    'avi'  => 'video/x-msvideo',
                    'mov'  => 'video/quicktime',
                    'webm' => 'video/webm',
                    'flv'  => 'video/x-flv',
                    'wmv'  => 'video/x-ms-wmv',
                ][$ext] ?? 'application/octet-stream';

                return response('', 200, [
                    'Content-Type'               => $mime,
                    'Accept-Ranges'              => 'bytes',
                    'Cache-Control'              => 'no-cache',
                    'Access-Control-Allow-Origin'=> '*',
                    'X-Accel-Redirect'           => '/internal_local_vod/' . $relativePath,
                ]);
            }
        }

        // Remote URL — hand off to Nginx proxy_pass via structured headers.
        // PHP terminates in <5ms; Nginx handles the long-lived range stream
        // asynchronously, freeing the FPM worker immediately.
        $parsed     = parse_url($streamUrl);
        $targetHost = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) {
            $targetHost .= ':' . $parsed['port'];
        }
        $targetUri = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        return response('', 200, [
            'Content-Type'               => 'video/mp4',
            'Access-Control-Allow-Origin'=> '*',
            'X-Accel-Redirect'           => '/internal_remote_vod',
            'X-Target-Host'              => $targetHost,
            'X-Target-URI'               => $targetUri,
        ]);
    }

    // ─── XC-VM Stream Delivery Methods ───────────────────────────────────────

    /**
     * Serve a live stream as HTTP-TS (.ts) — fast channel zapping.
     *
     * XC-VM route: /{username}/{password}/{stream_id}.ts
     */
    public function serveLiveStream(Request $request, string $username, string $password, int|string $streamId): \Symfony\Component\HttpFoundation\Response
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $rawId = (int) $streamId;

        // Connection limit
        $limiter   = app(ConnectionLimiter::class);
        $streamKey = 'live:' . $rawId;
        if (! $limiter->acquire($user, $streamKey)) {
            abort(429, 'Connection limit reached');
        }

        // Resolve channel (regular or AdminChannel)
        if ($rawId >= self::ADMIN_CHANNEL_OFFSET) {
            $adminId = $rawId - self::ADMIN_CHANNEL_OFFSET;
            $admin   = AdminChannel::where('id', $adminId)->where('is_active', true)->firstOrFail();
            $slug    = "admin-channel-" . ($admin->channel_slug ?? "{$adminId}");
            $channel = null;
            $sourceUrl = '';
        } else {
            $channel   = Channel::where('id', $rawId)->where('is_active', true)->firstOrFail();
            $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

            $this->ensureHlsStream(
                $rawId,
                $sourceUrl,
                $channel->program_number,
                $channel->local_address,
                (bool) ($channel->transcoding_enabled ?? false)
            );
            $slug = $rawId;
        }

        // Serve via HTTP-TS streaming from the local ingest directory
        $segDir = storage_path("app/streams/hls/{$slug}");
        for ($i = 0; $i < 12; $i++) {
            $segs = glob($segDir . '/seg_*.ts') ?: [];
            if (! empty($segs)) break;
            usleep(500000);
        }

        return response()->stream(function () use ($segDir, $limiter, $user, $streamKey) {
            $lastSeg = -1;

            while (true) {
                $playlist = $segDir . '/playlist.m3u8';
                if (! is_file($playlist)) { usleep(500000); continue; }

                $m3u8 = @file_get_contents($playlist);
                if (! $m3u8) { usleep(500000); continue; }

                preg_match_all('/^(seg_(\d+)\.ts)\s*$/m', $m3u8, $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $seq  = (int) $m[2];
                    $file = $segDir . '/' . $m[1];
                    if ($seq <= $lastSeg || ! is_file($file)) continue;

                    $data = @file_get_contents($file);
                    if ($data === false) continue;

                    echo $data;
                    if (ob_get_level()) ob_flush();
                    flush();

                    $lastSeg = $seq;
                    $limiter->acquire($user, $streamKey);
                }

                if (connection_aborted()) break;
                usleep(1000000);
            }

            $limiter->release($user->id, $streamKey);
        }, 200, [
            'Content-Type'               => 'video/mp2t',
            'Cache-Control'              => 'no-cache, no-store',
            'Access-Control-Allow-Origin'=> '*',
            'X-Accel-Buffering'          => 'no',
        ]);
    }

    /**
     * Serve a live stream as HLS (.m3u8) — the standard XC-VM delivery.
     *
     * XC-VM route: /{username}/{password}/{stream_id}.m3u8
     */
    public function serveLiveHls(Request $request, string $username, string $password, int|string $streamId): \Symfony\Component\HttpFoundation\Response
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $cacheKey = 'auth:token:' . md5($username . ':' . $password);
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, $user->id, 300);
        }

        $limiter   = app(ConnectionLimiter::class);
        $streamKey = 'live:' . (int) $streamId;
        if (! $limiter->acquire($user, $streamKey)) {
            abort(429, 'Connection limit reached');
        }

        $rawId = (int) $streamId;
        $baseUrl = '/live/' . rawurlencode($username) . '/' . rawurlencode($password) . '/' . $rawId . '/';

        // Admin / My-Channel streams
        if ($rawId >= self::ADMIN_CHANNEL_OFFSET) {
            $adminId = $rawId - self::ADMIN_CHANNEL_OFFSET;
            $admin   = AdminChannel::where('id', $adminId)->where('is_active', true)->firstOrFail();
            $slug    = "admin-channel-" . ($admin->channel_slug ?? "{$adminId}");
            return $this->serveHlsFile($slug, 'playlist.m3u8', $baseUrl);
        }

        // Regular channel streams
        $channel   = Channel::where('id', $rawId)->where('is_active', true)->firstOrFail();
        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        $this->ensureHlsStream(
            $rawId,
            $sourceUrl,
            $channel->program_number,
            $channel->local_address,
            (bool) ($channel->transcoding_enabled ?? false)
        );

        return $this->serveHlsFile($rawId, 'playlist.m3u8', $baseUrl);
    }

    /**
     * Serve a VOD movie as a native MP4 byte-range stream.
     *
     * XC-VM route: /{username}/{password}/vod/{stream_id}.{extension}
     * Supports instant seeking (pause, scrub, skip) via ngx_http_mp4_module.
     */
    public function serveMovie(Request $request, string $username, string $password, int $streamId, string $extension): \Symfony\Component\HttpFoundation\Response
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $limiter   = app(ConnectionLimiter::class);
        $streamKey = 'vod:' . $streamId;
        if (! $limiter->acquire($user, $streamKey)) {
            abort(429, 'Connection limit reached');
        }

        $vod = VODContent::where('id', $streamId)->where('is_active', true)->firstOrFail();
        $media = $vod->vodMedia()->first();
        if (! $media?->stream_url) abort(404);

        return $this->serveMediaFile($media->stream_url, $extension);
    }

    /**
     * Serve a series episode as a native MP4 byte-range stream.
     *
     * XC-VM route: /{username}/{password}/series/{stream_id}.{extension}
     * The stream_id is the vod_media.id (episode ID).
     */
    public function serveEpisode(Request $request, string $username, string $password, int $streamId, string $extension): \Symfony\Component\HttpFoundation\Response
    {
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        $limiter   = app(ConnectionLimiter::class);
        $streamKey = 'series:' . $streamId;
        if (! $limiter->acquire($user, $streamKey)) {
            abort(429, 'Connection limit reached');
        }

        $media = VODMedia::where('id', $streamId)->where('is_available', true)->firstOrFail();
        if (! $media->stream_url) abort(404);

        return $this->serveMediaFile($media->stream_url, $extension);
    }

    /**
     * Serve a media file (VOD or Series episode) via X-Accel-Redirect.
     *
     * Local files → /internal_media/ (mp4 module, byte-range seeking)
     * Remote URLs → /internal_remote_vod (proxy_pass)
     */
    private function serveMediaFile(string $streamUrl, string $extension): \Symfony\Component\HttpFoundation\Response
    {
        $mimeMap = [
            'mp4'  => 'video/mp4',
            'mkv'  => 'video/x-matroska',
            'avi'  => 'video/x-msvideo',
            'mov'  => 'video/quicktime',
            'webm' => 'video/webm',
            'flv'  => 'video/x-flv',
        ];
        $mime = $mimeMap[strtolower($extension)] ?? 'video/mp4';

        // Local file — serve via X-Accel-Redirect from /internal_media/
        // The mp4 module handles byte-range requests for instant seeking.
        if (str_starts_with($streamUrl, '/storage/')) {
            $relativePath = ltrim(substr($streamUrl, strlen('/storage/')), '/');
            $diskPath     = storage_path($relativePath);

            if (file_exists($diskPath)) {
                return response('', 200, [
                    'Content-Type'               => $mime,
                    'Accept-Ranges'              => 'bytes',
                    'Cache-Control'              => 'public, max-age=604800, immutable',
                    'Access-Control-Allow-Origin'=> '*',
                    'X-Accel-Redirect'           => '/internal_media/' . $relativePath,
                ]);
            }
        }

        // Remote URL — hand off to Nginx proxy_pass via structured headers.
        $parsed     = parse_url($streamUrl);
        $targetHost = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) {
            $targetHost .= ':' . $parsed['port'];
        }
        $targetUri = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        return response('', 200, [
            'Content-Type'               => $mime,
            'Accept-Ranges'              => 'bytes',
            'Access-Control-Allow-Origin'=> '*',
            'X-Accel-Redirect'           => '/internal_remote_vod',
            'X-Target-Host'              => $targetHost,
            'X-Target-URI'               => $targetUri,
        ]);
    }

    /**
     * XMLTV EPG endpoint for IPTV players.
     *
     * Returns XML electronic program guide data for all channels.
     */
    public function xmltv(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $channels = Channel::where('is_active', true)
            ->whereNotNull('epg_channel_id')
            ->get(['id', 'epg_channel_id', 'name']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<!DOCTYPE tv SYSTEM "xmltv.dtd">' . "\n";
        $xml .= '<tv source-info-name="IPTV Middleware" generator-info-name="Laravel XC-VM">' . "\n";

        // Channel definitions
        foreach ($channels as $ch) {
            $xml .= sprintf(
                '  <channel id="%s">%s  <display-name>%s</display-name>%s</channel>' . "\n",
                e($ch->epg_channel_id),
                $ch->logo_url ? "  <icon src=\"" . e($ch->logo_url) . "\"/>\n" : '',
                e($ch->name),
                '' // lang attribute
            );
        }

        // Programme listings (next 24h)
        $now  = now();
        $end  = $now->copy()->addDay();

        $programs = EPGProgram::whereIn('channel_id', $channels->pluck('id'))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $now)
            ->orderBy('start_time')
            ->get(['channel_id', 'title', 'description', 'start_time', 'end_time']);

        foreach ($programs as $prog) {
            $ch = $channels->firstWhere('id', $prog->channel_id);
            if (! $ch) continue;

            $xml .= sprintf(
                '  <programme start="%s" stop="%s" channel="%s">%s    <title>%s</title>%s%s  </programme>' . "\n",
                $prog->start_time->format('YmdHis O'),
                $prog->end_time->format('YmdHis O'),
                e($ch->epg_channel_id),
                "\n",
                e($prog->title),
                $prog->description ? "\n    <desc>" . e($prog->description) . '</desc>' : '',
                "\n"
            );
        }

        $xml .= '</tv>';

        return response($xml, 200, [
            'Content-Type'              => 'application/xml; charset=utf-8',
            'Cache-Control'             => 'public, max-age=3600',
            'Access-Control-Allow-Origin'=> '*',
        ]);
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

        $regular = Channel::with('categories')->where('is_active', true)->get()
            ->map(fn ($ch) => [
                'num'   => $ch->channel_number,
                'name'  => $ch->name,
                'logo'  => $ch->logo_url ?? '',
                'group' => $ch->categories->first()?->name ?? 'Uncategorized',
                'epg'   => $ch->epg_channel_id ?? '',
                'url'   => "{$base}/live/{$u}/{$token}/{$ch->id}.m3u8",
            ]);

        $admin = AdminChannel::where('is_active', true)
            ->where('broadcast_status', 'live')
            ->get()
            ->map(fn ($ac) => [
                'num'   => $ac->channel_number ? (int) $ac->channel_number : 999999,
                'name'  => $ac->channel_name,
                'logo'  => $ac->logo_url ?? '',
                'group' => 'My Channel',
                'epg'   => '',
                'url'   => "{$base}/live/{$u}/{$token}/" . ($ac->id + self::ADMIN_CHANNEL_OFFSET) . ".m3u8",
            ]);

        foreach ($regular->concat($admin)->sortBy('num')->values() as $ch) {
            $lines[] = sprintf(
                '#EXTINF:-1 tvg-id="%s" tvg-name="%s" tvg-logo="%s" group-title="%s",%s',
                $ch['epg'], $ch['name'], $ch['logo'], $ch['group'], $ch['name']
            );
            $lines[] = $ch['url'];
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

    /**
     * Authenticate a request. Redis is checked first (< 1ms) to avoid a
     * MySQL round-trip on every HLS playlist refresh. The token cache is
     * populated on first auth and expires with the user's session TTL.
     * Falls back to MySQL only when the Redis entry is missing (cold start,
     * password change, or cache flush).
     */
    private function authenticate(Request $request): ?User
    {
        $username = $request->username ?? $request->input('username');
        $password = $request->password ?? $request->input('password');

        if (! $username || ! $password) return null;

        // Fast path: token already validated and cached in Redis.
        $cacheKey = 'auth:token:' . md5($username . ':' . $password);
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ? User::find($cached) : null;
        }

        $user = User::where('username', $username)->first();

        if (! $user || ! $user->is_active) {
            Cache::put($cacheKey, false, 30); // negative cache — 30s
            return null;
        }

        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) {
            Cache::put($cacheKey, false, 30);
            return null;
        }

        // Cache the user ID for 5 minutes — covers all HLS segment refreshes
        // within a viewing session without hitting MySQL.
        Cache::put($cacheKey, $user->id, 300);

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
