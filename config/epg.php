<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EPG (Electronic Program Guide) Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('EPG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | EPG Sources
    |--------------------------------------------------------------------------
    */

    'sources' => [
        'xmltv' => [
            'enabled' => env('EPG_XMLTV_ENABLED', true),
            'url' => env('EPG_XMLTV_URL', ''),
            'fetch_interval' => env('EPG_XMLTV_FETCH_INTERVAL', 3600),
            'timeout' => env('EPG_XMLTV_TIMEOUT', 300),
            'cache_ttl' => env('EPG_XMLTV_CACHE_TTL', 7200),
        ],

        'custom' => [
            'enabled' => env('EPG_CUSTOM_ENABLED', false),
            'endpoint' => env('EPG_CUSTOM_ENDPOINT', ''),
            'api_key' => env('EPG_CUSTOM_API_KEY', ''),
        ],

        'gracenote' => [
            'enabled' => env('EPG_GRACENOTE_ENABLED', false),
            'client_id' => env('EPG_GRACENOTE_CLIENT_ID', ''),
            'api_key' => env('EPG_GRACENOTE_API_KEY', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Storage
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'driver' => env('EPG_STORAGE_DRIVER', 'database'),
        'cache_driver' => env('EPG_CACHE_DRIVER', 'redis'),
        'cache_ttl' => env('EPG_CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Data Processing
    |--------------------------------------------------------------------------
    */

    'processing' => [
        'timezone' => env('EPG_TIMEZONE', 'UTC'),
        'date_format' => env('EPG_DATE_FORMAT', 'Y-m-d H:i:s'),
        'max_programs_per_channel' => env('EPG_MAX_PROGRAMS', 500),
        'look_ahead_days' => env('EPG_LOOK_AHEAD_DAYS', 7),
        'look_back_days' => env('EPG_LOOK_BACK_DAYS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Output Formats
    |--------------------------------------------------------------------------
    */

    'output' => [
        'xmltv' => [
            'enabled' => true,
            'encoding' => 'UTF-8',
            'compress' => true,
        ],
        'json' => [
            'enabled' => true,
            'pretty' => false,
        ],
        'm3u' => [
            'enabled' => true,
            'include_epg' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Caching
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('EPG_CACHE_ENABLED', true),
        'prefix' => env('EPG_CACHE_PREFIX', 'epg_'),
        'ttl' => env('EPG_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('EPG_LOGGING_ENABLED', true),
        'channel' => 'epg',
        'log_fetches' => true,
        'log_errors' => true,
    ],

];
