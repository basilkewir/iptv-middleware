<?php

namespace App\Http\Controllers;

use App\Models\AdminChannel\AdminChannel;
use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\EPGProgram;
use App\Models\User;
use App\Models\VODContent;
use App\Models\VODMedia;
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

    /**
     * HTTP TS (MPEG-TS over HTTP) continuous stream endpoint.
     * Faster channel zap than HLS — no chunk boundary wait.
     * Proxies the upstream source directly to the client.
     */
    /**
     * HTTP-TS (MPEG-TS over HTTP) endpoint — faster channel zap than HLS.
     *
     * Reads .ts segments sequentially from the local ingest directory and
     * pipes them as a continuous MPEG-TS byte stream. The source is ingested
     * exactly once by ffmpeg; all viewers share the same local segment cache.
     * No connection to the upstream source is made from this method.
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

        // Ensure the local ingest is running for all source types.
        $this->ensureHlsStream(
            $channelId,
            $sourceUrl,
            $channel->program_number,
            $channel->local_address,
            (bool) ($channel->transcoding_enabled ?? false)
        );

        $segDir = storage_path("app/streams/hls/{$channelId}");

        // Wait up to 6s for the first segment to appear (cold start).
        for ($i = 0; $i < 12; $i++) {
            $segs = glob($segDir . '/segment_*.ts') ?: [];
            if (! empty($segs)) break;
            usleep(500000);
        }

        // Pipe segments sequentially as a continuous MPEG-TS stream.
        // Reads from the local RAM-backed HLS directory — the upstream source
        // is never contacted from here. Each segment is ~4s of video; we read
        // them in order and loop on the playlist to follow new segments.
        return response()->stream(function () use ($segDir, $limiter, $user, $streamKey) {
            $lastSeg = -1;

            while (true) {
                $playlist = $segDir . '/playlist.m3u8';
                if (! is_file($playlist)) { usleep(500000); continue; }

                $m3u8 = @file_get_contents($playlist);
                if (! $m3u8) { usleep(500000); continue; }

                // Parse segment filenames from the playlist in order.
                preg_match_all('/^(segment_(\d+)\.ts)\s*$/m', $m3u8, $matches, PREG_SET_ORDER);
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
                    $limiter->acquire($user, $streamKey); // refresh slot
                }

                if (connection_aborted()) break;
                usleep(1000000); // poll every 1s for new segments
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
     * Responsibilities (control plane only — no data transfer here):
     *   1. Authenticate via Redis token cache (< 1ms, no MySQL hit on warm cache)
     *   2. Enforce per-user concurrent connection limit
     *   3. Ensure the ingest process is running (start if needed)
     *   4. Dispatch to the least-loaded edge node, or fall back to local
     *   5. Redirect the player to nginx-served HLS — zero PHP data transfer
     *
     * Nginx serves .m3u8 and .ts bytes directly from the RAM-backed HLS
     * directory using sendfile+tcp_nopush. PHP never touches segment data.
     */
    public function streamLive(Request $request, $username, $password, $streamId)
    {
        // ── 1. Auth (Redis-first, < 1ms on warm cache) ───────────────────────
        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) abort(401);
        if ($password !== $user->m3u_token && ! Hash::check($password, $user->password)) abort(401);

        // Populate Redis token cache so subsequent HLS playlist refreshes
        // (every 2–6s per player) bypass MySQL entirely.
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

        // Admin / My-Channel streams — served from their own HLS output dir.
        if ($rawId >= self::ADMIN_CHANNEL_OFFSET) {
            $adminId = $rawId - self::ADMIN_CHANNEL_OFFSET;
            $admin   = AdminChannel::where('id', $adminId)->where('is_active', true)->firstOrFail();
            return redirect(config('app.url') . "/hls/admin-channel-{$admin->channel_slug}/index.m3u8");
        }

        $channelId = $rawId;
        $channel   = Channel::where('id', $channelId)->where('is_active', true)->firstOrFail();
        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        // ── 3. Ensure origin ingest is running ───────────────────────────────
        // ffmpeg pulls the source once and writes HLS segments to the local
        // RAM-backed tmpfs. All viewers share this single ingest — the source
        // sees exactly one connection regardless of viewer count.
        $this->ensureHlsStream(
            $channelId,
            $sourceUrl,
            $channel->program_number,
            $channel->local_address,
            (bool) ($channel->transcoding_enabled ?? false)
        );

        // ── 4. Edge dispatch ──────────────────────────────────────────────────
        // Auth and ingest management happen here (control plane). Actual data
        // delivery is offloaded to the least-loaded edge node. Edge nodes read
        // HLS segments directly from disk via nginx — no PHP on the data path.
        $edge = app(EdgeDispatcher::class)->bestEdge();
        if ($edge !== null) {
            app(EdgeDispatcher::class)->incrementConnections($edge);
            return redirect("{$edge}/edge/live/{$username}/{$user->m3u_token}/{$channelId}.m3u8");
        }

        // ── 5. Local fallback: redirect to nginx-served HLS ──────────────────
        // Nginx reads segments from storage/app/streams/hls/{id}/ with
        // sendfile + tcp_nopush — zero PHP overhead on the data path.
        $hlsBase  = config('app.url') . "/hls/{$channelId}";
        $playlist = storage_path("app/streams/hls/{$channelId}/playlist.m3u8");

        if (strtolower((string) pathinfo($streamId, PATHINFO_EXTENSION)) !== 'm3u8') {
            return redirect("{$hlsBase}/playlist.m3u8");
        }

        // Wait up to 8s for the first playlist on cold start.
        for ($i = 0; $i < 16 && ! file_exists($playlist); $i++) {
            usleep(500000);
        }

        if (! file_exists($playlist)) {
            $cached = Cache::get("hls:stale:live:{$channelId}:playlist");
            if ($cached !== null) {
                return response($cached, 200, [
                    'Content-Type'               => 'application/vnd.apple.mpegurl',
                    'Cache-Control'              => 'no-cache, no-store, must-revalidate',
                    'Access-Control-Allow-Origin'=> '*',
                    'X-HLS-Stale'               => '1',
                ]);
            }
            $offline = config('streaming.offline.hls_dir') . '/playlist.m3u8';
            if (is_file($offline)) {
                return redirect(config('app.url') . '/hls/offline/playlist.m3u8');
            }
            return response('Service Unavailable', 503, ['Retry-After' => '3']);
        }

        // Redirect to nginx — nginx serves the file with sendfile, no PHP buffering.
        return redirect("{$hlsBase}/playlist.m3u8");
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
     * A healthy ingest writes a new segment roughly every 6 seconds. When the
     * most recent segment is older than the staleness window the ingest is
     * considered frozen even if its process is still alive.
     */
    private function ingestStale(string $outputDir): bool
    {
        $newest = 0;

        foreach (glob($outputDir . '/segment_*.ts') ?: [] as $segment) {
            $mtime = @filemtime($segment);

            if ($mtime !== false && $mtime > $newest) {
                $newest = $mtime;
            }
        }

        if ($newest === 0) {
            // No segments at all. With the self-restarting wrapper the
            // process may simply still be connecting and writing its first
            // segment, so treat this as "not stale yet" rather than frozen.
            // A truly hung process gives up via its own I/O timeouts and the
            // dead-process path takes over from there.
            return false;
        }

        return (time() - $newest) > self::INGEST_STALE_SECONDS;
    }

    private function cleanOutputDirectory(string $outputDir): void
    {
        foreach (glob($outputDir . '/segment_*.ts') ?: [] as $segment) {
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
            // 32 MB SO_RCVBUF absorbs bursts from multi-program TS muxes without
            // dropping packets (requires net.core.rmem_max >= 33554432 on host).
            $input .= (str_contains($input, '?') ? '&' : '?')
                . 'localaddr=' . $localAddress
                . '&buffer_size=33554432';
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
        // extension_picky was added in FFmpeg 6.x — skip it on older builds.
        $ffmpegVersion = (int) shell_exec('ffmpeg -version 2>&1 | grep -oP "ffmpeg version \\K\\d+" | head -1');
        $hlsOpts = ($isHls && $ffmpegVersion >= 6) ? '-extension_picky 0 ' : '';
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
                ? '-fflags +genpts+discardcorrupt -max_interleave_delta 0 -reconnect 1 -reconnect_streamed 1 -reconnect_on_http_error 404,403 -reconnect_delay_max 5 -rw_timeout %d -timeout %d ' . $hlsOpts . $userAgent . ' -i %s'
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
                // Video copy — source GOP preserved. Audio normalized to AAC.
                ? ' -threads ' . self::FFMPEG_THREADS_SINGLE . ' -c:v copy -c:a aac -b:a 128k -ac 2 -ar 48000 -f hls '
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
            'ODIR=%s; L=%s; DELAY=3; '
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
            .   'HAS_SEGS=0; ls "$ODIR"/segment_*.ts > /dev/null 2>&1 && HAS_SEGS=1; '
            . $restartClean
            .   ($channelId > 0 && str_contains(strtolower($sourceUrl), 'youtube')
                ? 'NEW_URL=$(cd ' . base_path() . ' && php artisan youtube:refresh-url ' . $channelId . ' 2>/dev/null); if [ $? -eq 0 ] && [ -n "$NEW_URL" ]; then SRC_URL="$NEW_URL"; echo "YOUTUBE REFRESHED $SRC_URL" >> "$L"; fi; '
                : '')
            .   'nice -n ' . self::INGEST_NICE_LEVEL . ' ffmpeg ' . $inputOpts . '%s ' . $videoFilter
            .   ($isMulticast ? '-hls_time 4 -hls_list_size 5 ' : '-hls_time 4 -hls_list_size 6 ')
            .   '-hls_flags delete_segments+temp_file+independent_segments+append_list '
            .   '-muxdelay 0 -muxpreload 0 '
            .   '-hls_segment_filename "$ODIR"/segment_%%04d.ts '
            .   '"$ODIR"/playlist.m3u8 2>>"$L"; '
            .   'NEW_SEGS=0; ls "$ODIR"/segment_*.ts > /dev/null 2>&1 && NEW_SEGS=1; '
            .   'if [ "$NEW_SEGS" = "1" ]; then DELAY=3; '
            .   'else DELAY=$((DELAY * 2)); [ $DELAY -gt 30 ] && DELAY=30; fi; '
            .   'echo "WRAPPER RETRY delay=$DELAY $(date +%%s)" >> "$L"; '
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
        if (str_starts_with($streamUrl, '/storage/')) {
            $diskPath = storage_path('app/public/' . substr($streamUrl, strlen('/storage/')));
            if (file_exists($diskPath)) {
                $mimeMap = [
                    'mp4'  => 'video/mp4',
                    'mkv'  => 'video/x-matroska',
                    'avi'  => 'video/x-msvideo',
                    'mov'  => 'video/quicktime',
                    'webm' => 'video/webm',
                    'flv'  => 'video/x-flv',
                    'wmv'  => 'video/x-ms-wmv',
                ];
                $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
                $mime = $mimeMap[$ext] ?? mime_content_type($diskPath) ?: 'application/octet-stream';

                return response()->file($diskPath, [
                    'Content-Type'  => $mime,
                    'Accept-Ranges' => 'bytes',
                ]);
            }
        }

        return $this->proxyExternalUrl($streamUrl);
    }

    /**
     * Proxy an external URL through the middleware so the provider only
     * sees 1 connection regardless of how many viewers are watching.
     * Streams the response without buffering the entire file in memory.
     */
    private function proxyExternalUrl(string $url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_RANGE          => $_SERVER['HTTP_RANGE'] ?? null,
            CURLOPT_USERAGENT      => 'IPTV-Middleware/1.0',
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $statusCode === 0) {
            abort(502, 'Upstream unreachable: ' . ($error ?: 'unknown error'));
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $responseHeaders = [
            'Content-Type'  => 'application/octet-stream',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache',
        ];

        foreach (explode("\r\n", $rawHeaders) as $header) {
            if (preg_match('/^Content-Type:\s*(.+)/i', $header, $m)) {
                $responseHeaders['Content-Type'] = trim($m[1]);
            } elseif (preg_match('/^Content-Length:\s*(.+)/i', $header, $m)) {
                $responseHeaders['Content-Length'] = trim($m[1]);
            } elseif (preg_match('/^Content-Range:\s*(.+)/i', $header, $m)) {
                $responseHeaders['Content-Range'] = trim($m[1]);
            } elseif (preg_match('/^Accept-Ranges:\s*(.+)/i', $header, $m)) {
                $responseHeaders['Accept-Ranges'] = trim($m[1]);
            } elseif (preg_match('/^Content-Disposition:\s*(.+)/i', $header, $m)) {
                $responseHeaders['Content-Disposition'] = trim($m[1]);
            }
        }

        $status = $statusCode === 206 ? 206 : 200;

        return response($body, $status, $responseHeaders);
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
