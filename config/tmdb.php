<?php

return [
    'api_key' => env('TMDB_API_KEY'),
    'api_base_url' => env('TMDB_API_BASE_URL', 'https://api.themoviedb.org/3'),
    'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p'),
    'language' => env('TMDB_LANGUAGE', 'en-US'),
    'region' => env('TMDB_REGION', 'US'),
    'cache_ttl' => env('TMDB_CACHE_TTL', 86400),

    'auto_populate' => [
        'enabled' => env('TMDB_AUTO_POPULATE_ENABLED', true),
        'on_upload' => env('TMDB_AUTO_POPULATE_ON_UPLOAD', true),
        'match_threshold' => env('TMDB_AUTO_POPULATE_MATCH_THRESHOLD', 70),
    ],

    'rate_limit' => [
        'max_requests' => env('TMDB_RATE_LIMIT_MAX_REQUESTS', 40),
        'decay_minutes' => env('TMDB_RATE_LIMIT_DECAY_MINUTES', 10),
    ],

    'fallback' => [
        'max_retries' => env('TMDB_FALLBACK_MAX_RETRIES', 3),
        'retry_delay' => env('TMDB_FALLBACK_RETRY_DELAY', 100),
        'timeout' => env('TMDB_FALLBACK_TIMEOUT', 30),
    ],
];
