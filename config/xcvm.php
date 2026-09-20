<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standalone Mode (no XC-VM dependency)
    |--------------------------------------------------------------------------
    |
    | The middleware now handles all streaming directly using the XC-VM-style
    | split-stream architecture:
    |   - Background FFmpeg processes ingest once per channel
    |   - Nginx serves HLS segments from disk to all viewers
    |   - PHP only handles authentication and control-plane operations
    |
    */

    'enabled' => false,

    // All XC-VM integration is disabled. The middleware is fully standalone.
    'url' => '',
    'port' => 0,
    'access_code' => '',
    'api_key' => '',
    'timeout' => 0,
    'retries' => 0,
    'live_sync' => false,
    'full_resync_schedule' => '',
    'prune_remote' => false,
    'start_streams_on_sync' => false,
    'proxy_player' => false,
    'proxy_url' => '',
    'proxy_port' => 0,
    'proxy_timeout' => 0,
    'vod_url_base' => '',
    'line_password' => 'm3u_token',
    'line_password_length' => 16,

    /*
    |--------------------------------------------------------------------------
    | HLS Tuning (standalone mode)
    |--------------------------------------------------------------------------
    */

    'hls_segment_duration' => (int) env('XC_VM_HLS_SEGMENT_DURATION', 4),
    'hls_playlist_size' => (int) env('XC_VM_HLS_PLAYLIST_SIZE', 5),
    'hls_keyframe_interval' => (int) env('XC_VM_HLS_KEYFRAME_INTERVAL', 100),

    'stream_health_check_interval' => (int) env('XC_VM_HEALTH_CHECK_INTERVAL', 60),
    'max_playlist_age' => (int) env('XC_VM_MAX_PLAYLIST_AGE', 120),

];
