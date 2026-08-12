<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Streaming Server Configuration
    |--------------------------------------------------------------------------
    */

    'server' => [
        'name' => env('STREAMING_SERVER_NAME', 'IPTV Middleware'),
        'host' => env('STREAMING_SERVER_HOST', '0.0.0.0'),
        'port' => env('STREAMING_SERVER_PORT', 8080),
    ],

    /*
    |--------------------------------------------------------------------------
    | HLS (HTTP Live Streaming) Configuration
    |--------------------------------------------------------------------------
    */

    'hls' => [
        'enabled' => env('HLS_ENABLED', true),
        'port' => env('HLS_PORT', 8088),
        'segment_duration' => env('HLS_SEGMENT_DURATION', 6),
        'playlist_size' => env('HLS_PLAYLIST_SIZE', 10),
        'output_path' => env('HLS_OUTPUT_PATH', storage_path('app/hls')),
        'temp_path' => env('HLS_TEMP_PATH', storage_path('app/hls_temp')),
        'cleanup_interval' => env('HLS_CLEANUP_INTERVAL', 300),
        'max_concurrent_streams' => env('HLS_MAX_CONCURRENT', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | RTMP Configuration
    |--------------------------------------------------------------------------
    */

    'rtmp' => [
        'enabled' => env('RTMP_ENABLED', true),
        'port' => env('RTMP_PORT', 1935),
        'chunk_size' => env('RTMP_CHUNK_SIZE', 4096),
        'max_connections' => env('RTMP_MAX_CONNECTIONS', 1000),
        'idle_timeout' => env('RTMP_IDLE_TIMEOUT', 30),
        'gop_cache' => env('RTMP_GOP_CACHE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stream Transcoding Settings
    |--------------------------------------------------------------------------
    */

    'transcoding' => [
        'enabled' => env('TRANSCODING_ENABLED', true),
        'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
        'default_video_bitrate' => env('DEFAULT_VIDEO_BITRATE', '3000k'),
        'default_audio_bitrate' => env('DEFAULT_AUDIO_BITRATE', '128k'),
        'default_resolution' => env('DEFAULT_RESOLUTION', '1920x1080'),
        'presets' => [
            'low' => [
                'video_bitrate' => '1000k',
                'audio_bitrate' => '96k',
                'resolution' => '854x480',
            ],
            'medium' => [
                'video_bitrate' => '3000k',
                'audio_bitrate' => '128k',
                'resolution' => '1280x720',
            ],
            'high' => [
                'video_bitrate' => '6000k',
                'audio_bitrate' => '192k',
                'resolution' => '1920x1080',
            ],
            'ultra' => [
                'video_bitrate' => '15000k',
                'audio_bitrate' => '320k',
                'resolution' => '3840x2160',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stream Authentication
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'enabled' => env('STREAM_AUTH_ENABLED', true),
        'token_expiry' => env('STREAM_TOKEN_EXPIRY', 3600),
        'ip_whitelist' => env('STREAM_IP_WHITELIST', []),
        'max_connections_per_user' => env('STREAM_MAX_CONNECTIONS_PER_USER', 3),
        'geo_blocking' => env('STREAM_GEO_BLOCKING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stream Recording
    |--------------------------------------------------------------------------
    */

    'recording' => [
        'enabled' => env('STREAM_RECORDING_ENABLED', false),
        'path' => env('STREAM_RECORDING_PATH', storage_path('app/recordings')),
        'format' => env('STREAM_RECORDING_FORMAT', 'mp4'),
        'max_duration' => env('STREAM_MAX_DURATION', 14400),
        'auto_cleanup' => env('STREAM_AUTO_CLEANUP', true),
        'cleanup_days' => env('STREAM_CLEANUP_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Load Balancing
    |--------------------------------------------------------------------------
    */

    'load_balancing' => [
        'enabled' => env('LOAD_BALANCING_ENABLED', false),
        'algorithm' => env('LOAD_BALANCING_ALGORITHM', 'round_robin'),
        'servers' => env('LOAD_BALANCING_SERVERS', []),
        'health_check_interval' => env('HEALTH_CHECK_INTERVAL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    */

    'cdn' => [
        'enabled' => env('CDN_ENABLED', false),
        'provider' => env('CDN_PROVIDER', 'cloudflare'),
        'domain' => env('CDN_DOMAIN'),
        'api_key' => env('CDN_API_KEY'),
        'edge_locations' => env('CDN_EDGE_LOCATIONS', []),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stream Quality Monitoring
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'enabled' => env('STREAM_MONITORING_ENABLED', true),
        'metrics_retention' => env('METRICS_RETENTION_DAYS', 30),
        'alert_threshold' => env('ALERT_THRESHOLD', 80),
        'notify_email' => env('ALERT_NOTIFY_EMAIL'),
        'check_interval' => env('MONITORING_CHECK_INTERVAL', 60),
    ],

];
