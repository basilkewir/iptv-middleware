<?php

return [

    /*
    |--------------------------------------------------------------------------
    | XC-VM Streaming Engine Integration
    |--------------------------------------------------------------------------
    |
    | XC-VM (https://github.com/Vateron-Media/XC_VM) is the streaming engine
    | behind this middleware. The middleware remains the source of truth and
    | admin panel (streambox); a sync bridge pushes channels, bouquets, users
    | and VOD to XC-VM through its admin API. Player traffic is proxied to
    | XC-VM so existing player links keep working.
    |
    */

    'enabled' => env('XC_VM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    | XC-VM admin API pattern:
    |   {protocol}://{server}:{port}/{accessCode}/?api_key={key}&action={action}
    */

    'url' => env('XC_VM_URL', 'http://127.0.0.1'),

    'port' => env('XC_VM_PORT', 80),

    'access_code' => env('XC_VM_ACCESS_CODE', ''),

    'api_key' => env('XC_VM_API_KEY', ''),

    // Admin API request timeout in seconds.
    'timeout' => (int) env('XC_VM_TIMEOUT', 20),

    // Retry a failed API call this many times before giving up (0 = no retry).
    'retries' => (int) env('XC_VM_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Sync Behaviour
    |--------------------------------------------------------------------------
    */

    // Enable live sync (model observers push changes to XC-VM in the background).
    'live_sync' => env('XC_VM_LIVE_SYNC', true),

    // Builder(s) for the scheduled full re-sync. Accepts cron-style or
    // Laravel shorthand intervals, e.g. 'everyFiveMinutes' -> everyFiveMinutes().
    'full_resync_schedule' => env('XC_VM_FULL_RESYNC_SCHEDULE', 'everyFiveMinutes'),

    // Delete remote objects that no longer exist locally during full sync.
    'prune_remote' => env('XC_VM_PRUNE_REMOTE', false),

    // Map each middleware channel to one XC-VM stream and keep the XC-VM stream
    // running for remote sources (start_stream after create/edit).
    'start_streams_on_sync' => env('XC_VM_START_STREAMS', true),

    /*
    |--------------------------------------------------------------------------
    | Player Proxy
    |--------------------------------------------------------------------------
    | When enabled, the middleware transparently proxies the Xtream player
    | endpoints (/player_api.php, /live/{u}/{p}/{id}, /movie/..., /series/...,
    | /get.php) to XC-VM so the engine serves the stream while player URLs
    | stay unchanged. XC-VM stays bound to 127.0.0.1 and is never reachable
    | directly from clients.
    |
    | The middleware keeps two responsibilities when the proxy is on:
    |   - it still authenticates every request against its own users table
    |     (source of truth), and
    |   - it translates middleware stream/movie/series ids to the XC-VM ids
    |     recorded in the xc_vm_mappings table, then streams the response
    |     from XC-VM through to the player.
    |
    | If XC-VM is unreachable or an entity is not yet mapped, the middleware
    | falls back to its own built-in HLS ingest / file serving, so nothing
    | breaks while a sync is pending.
    */

    'proxy_player' => env('XC_VM_PROXY_PLAYER', true),

    // Where the middleware should reach XC-VM for proxying. XC-VM should be
    // bound to 127.0.0.1 on a dedicated port (configured by install.sh) so it
    // is hidden from the internet.
    'proxy_url' => env('XC_VM_PROXY_URL', env('XC_VM_URL', 'http://127.0.0.1')),

    'proxy_port' => env('XC_VM_PROXY_PORT', env('XC_VM_PORT', 80)),

    // Streaming request timeout (seconds) for a proxied player request.
    'proxy_timeout' => (int) env('XC_VM_PROXY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | VOD / player URL base
    |--------------------------------------------------------------------------
    | When the engine receives a VOD/EPG request it needs URLs it can reach.
    | install.sh bridges the middleware's publicly served storage so XC-VM can
    | pull uploaded files over loopback. This base is used to translate a local
    | middleware storage path (e.g. /storage/vod/x.mp4) into the URL passed as
    | `stream_url` when a movie/episode is created in XC-VM.
    |
    | Leave blank to disable translation (raw stream_url is passed through).
    */

    'vod_url_base' => rtrim((string) env('XC_VM_VOD_URL_BASE', ''), '/'),

    /*
    |--------------------------------------------------------------------------
    | Password policy (users)
    |--------------------------------------------------------------------------
    | A user's XC-VM line password. Preferred: use their m3u_token so the
    | Xtream player password stays stable. Fallback generator for users that
    | have no token yet.
    */

    'line_password' => env('XC_VM_LINE_PASSWORD_SOURCE', 'm3u_token'),

    'line_password_length' => (int) env('XC_VM_LINE_PASSWORD_LENGTH', 16),

    /*
    |--------------------------------------------------------------------------
    | Ultra-Low Latency HLS Tuning
    |--------------------------------------------------------------------------
    | Controls the HLS output parameters for streams managed by XC-VM and
    | the middleware's fallback ingest. Lower values = faster channel zapping
    | and lower glass-to-glass latency, at the cost of slightly more CPU
    | and bandwidth overhead.
    |
    | segment_duration: Duration of each .ts segment in seconds (2 = fastest)
    | playlist_size:   Number of segments in the live playlist (3 = lowest latency)
    | keyframe_interval: GOP size in frames for transcoded streams (segment_duration × fps)
    */

    'hls_segment_duration' => (int) env('XC_VM_HLS_SEGMENT_DURATION', 2),

    'hls_playlist_size' => (int) env('XC_VM_HLS_PLAYLIST_SIZE', 3),

    'hls_keyframe_interval' => (int) env('XC_VM_HLS_KEYFRAME_INTERVAL', 50),

    /*
    |--------------------------------------------------------------------------
    | Stream Health Monitoring
    |--------------------------------------------------------------------------
    | How often (in seconds) the bridge checks whether a channel's source
    | is still fresh and pushes an updated URL to XC-VM when needed.
    */

    'stream_health_check_interval' => (int) env('XC_VM_HEALTH_CHECK_INTERVAL', 60),

    // Maximum age (seconds) of a playlist file before the bridge considers
    // the ingest dead and skips pushing the URL to XC-VM.
    'max_playlist_age' => (int) env('XC_VM_MAX_PLAYLIST_AGE', 120),

];