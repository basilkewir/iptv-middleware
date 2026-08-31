<?php

namespace App\Http\Controllers;

use App\Models\AdminChannel\AdminChannel;
use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\EPGProgram;
use App\Models\User;
use App\Models\VODContent;
use App\Models\VODMedia;
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

    // Stream a live channel — serves content inline (no redirect).
    // IPTV players often fail to follow 302 redirects for HLS, and
    // segment URLs in the playlist would resolve back into this route,
    // causing an infinite redirect loop to the playlist.
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
        $rawId = (int) $streamId;

        // Admin / My-Channel streams use offset IDs to live in a separate
        // ID space from regular channels.  Serve them directly from the
        // local HLS output written by MyChannelHlsService.
        if ($rawId >= self::ADMIN_CHANNEL_OFFSET) {
            $adminId = $rawId - self::ADMIN_CHANNEL_OFFSET;
            $admin = AdminChannel::where('id', $adminId)->where('is_active', true)->firstOrFail();

            return redirect(config('app.url') . "/hls/admin-channel-{$admin->channel_slug}/index.m3u8");
        }

        $channelId = $rawId;

        $channel = Channel::where('id', $channelId)->where('is_active', true)->firstOrFail();

        // Route through the local FFmpeg ingest pipeline so audio is
        // transcoded to AAC (required for Android TV and most IPTV players).
        // This applies to UDP/RTMP sources and Flussonic multicast re-streams.
        $this->ensureHlsStream($channelId, $channel->active_stream_url ?? $channel->stream_url, $channel->program_number, $channel->local_address, (bool) ($channel->transcoding_enabled ?? false));

        // Parse the streamId to determine file type
        $extension = strtolower(pathinfo($streamId, PATHINFO_EXTENSION));

        $hlsDir  = storage_path("app/streams/hls/{$channelId}");
        $hlsBase = config('app.url') . "/hls/{$channelId}";

        // Non-m3u8 requests (no extension or .ts) — always redirect to the
        // HLS playlist. Never expose the raw udp:// source URL to the player.
        if ($extension !== 'm3u8') {
            return redirect("{$hlsBase}/playlist.m3u8");
        }

        // .m3u8 request — wait up to 8 s for the multicast group reader to
        // produce its first playlist before giving up. This prevents players
        // from seeing a 503 on the very first request after a cold start and
        // interpreting it as "stream format not supported".
        $playlist = "{$hlsDir}/playlist.m3u8";
        $waited   = 0;
        while (! file_exists($playlist) && $waited < 8) {
            usleep(500000); // 0.5 s
            $waited++;
        }

        if (! file_exists($playlist)) {
            return response('Service Unavailable', 503, [
                'Retry-After'  => '3',
                'Cache-Control'=> 'no-cache, no-store, must-revalidate',
            ]);
        }

        $content = file_get_contents($playlist);
        if ($content === false) {
            return response('Service Unavailable', 503, [
                'Retry-After'  => '3',
                'Cache-Control'=> 'no-cache, no-store, must-revalidate',
            ]);
        }

        // Rewrite segment references to absolute /hls/ paths so the
        // player never needs to follow an external redirect.
        $content = preg_replace(
            '/^(?!#)(\S+\.ts)\s*$/m',
            $hlsBase . '/$1',
            $content
        );

        return response($content, 200, [
            'Content-Type'               => 'application/vnd.apple.mpegurl',
            'Cache-Control'              => 'no-cache, no-store, must-revalidate',
            'Access-Control-Allow-Origin'=> '*',
        ]);
    }

    /**
     * Ensure an HLS ingest process is running for the given channel.
     * Tracks the ingest by a per-channel PID file so each channel is ingested
     * exactly once and dead ingests can be respawned without scanning every
     * process (used by both on-demand playback and channels:ingest-all).
     *
     * The ingest is also considered dead when the process is still alive but
     * has stopped writing new segments (frozen stream). In that case the
     * process is killed and the output directory is wiped before restarting,
     * so a frozen ingest can never serve a stale playlist to clients.
     *
     * For multi-channel multicast sources (udp://@...) $programNumber selects
     * a single MPEG-TS program, which is what makes one multicast stream feed
     * many channel rows, each ingesting only its own program.
     */
    public function ensureHlsStream(int $channelId, string $sourceUrl, ?int $programNumber = null, ?string $localAddress = null, bool $transcode = false): void
    {
        $outputDir = storage_path("app/streams/hls/{$channelId}");
        $pidFile = $outputDir . '/ingest.pid';
        $heartbeat = $outputDir . '/.heartbeat';

        // Touch heartbeat every time a client requests this channel.
        // The wrapper checks this file — if nobody requests for 120s, it exits.
        @touch($heartbeat);

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
        // For UDP: +genpts fixes missing PTS after TS discontinuities,
        // +discardcorrupt drops damaged packets, -err_detect ignore_err skips
        // corrupt frames without stalling, -avoid_negative_ts make_zero fixes
        // DTS jumps that freeze the HLS muxer (the main cause of stalling).
        $inputOpts = $isMulticast
            ? '-fflags +genpts+discardcorrupt -err_detect ignore_err -avoid_negative_ts make_zero -max_interleave_delta 0 -rw_timeout %d -timeout %d -i %s'
            : ($isLiveHttp
                ? '-fflags +genpts+discardcorrupt -max_interleave_delta 0 -reconnect 1 -reconnect_streamed 1 -reconnect_on_http_error 404,403 -reconnect_delay_max 5 -rw_timeout %d -timeout %d ' . $userAgent . ' -i %s'
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

        $videoFilter = $transcode
            // GPU or CPU re-encode depending on transcoding_device setting.
            ? ($useGpu
                ? $liveMap . ' -c:v h264_nvenc -preset p4 -tune ll -rc vbr -cq 28 -b:v 0 -maxrate 4000k -bufsize 8000k -c:a aac -b:a 48k -ac 2 -ar 48000 -f hls '
                : ' -threads ' . self::FFMPEG_THREADS_TRANSCODE . $liveMap . ' -c:v libx264 -preset veryfast -crf 26 -tune zerolatency -c:a aac -b:a 48k -ac 2 -ar 48000 -f hls ')
            : ($isMulticast
                // Copy both streams — no audio transcode on UDP channels.
                // mp3/mp2/ac3 plays natively on all modern IPTV players.
                // Transcoding 64 channels simultaneously was the primary
                // cause of load 32+ and the resulting HOLD stalls.
                ? ' -threads ' . self::FFMPEG_THREADS_SINGLE . ' -c:v copy -c:a copy -f hls '
                : ' -threads ' . self::FFMPEG_THREADS_SINGLE . $liveMap . ' -c:v copy -c:a copy -f hls ');

        // All channels run permanently — no idle timeout.  This ensures
        // instant zapping: every channel has its ingest already running
        // and segments ready when the user switches to it.

        $isYouTube = str_contains(strtolower($sourceUrl), 'youtube');
        $ytInit = $isYouTube && $channelId > 0 ? 'SRC_URL=' . escapeshellarg($sourceUrl) . '; ' : '';

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
            .   '[ "$HAS_SEGS" = "1" ] && rm -f "$ODIR"/segment_*.ts "$ODIR"/playlist.m3u8; '
            .   ($channelId > 0 && str_contains(strtolower($sourceUrl), 'youtube')
                ? 'NEW_URL=$(cd ' . base_path() . ' && php artisan youtube:refresh-url ' . $channelId . ' 2>/dev/null); if [ $? -eq 0 ] && [ -n "$NEW_URL" ]; then SRC_URL="$NEW_URL"; echo "YOUTUBE REFRESHED $SRC_URL" >> "$L"; fi; '
                : '')
            .   'nice -n ' . self::INGEST_NICE_LEVEL . ' ffmpeg ' . $inputOpts . '%s ' . $videoFilter
            .   ($isMulticast ? '-hls_time 1 -hls_list_size 2 ' : '-hls_time 6 -hls_list_size 5 ')
            .   '-hls_flags delete_segments+temp_file+independent_segments+append_list '
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
            self::FFMPEG_READ_TIMEOUT_US,
            self::FFMPEG_READ_TIMEOUT_US,
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

        return redirect($streamUrl);
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
