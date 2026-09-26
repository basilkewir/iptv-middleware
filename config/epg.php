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
    | Open-source XMLTV feeds. Each source maps to a country or service.
    | The UpdateEPG command iterates active sources and imports programs.
    */
    'sources' => [
        'iptv-epg.org-us' => [
            'name'     => 'US - iptv-epg.org',
            'url'      => 'https://iptv-epg.org/files/epg-us.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'US',
            'enabled'  => env('EPG_US_ENABLED', true),
        ],
        'iptv-epg.org-gb' => [
            'name'     => 'UK - iptv-epg.org',
            'url'      => 'https://iptv-epg.org/files/epg-gb.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'GB',
            'enabled'  => env('EPG_GB_ENABLED', true),
        ],
        'iptv-epg.org-ca' => [
            'name'     => 'CA - iptv-epg.org',
            'url'      => 'https://iptv-epg.org/files/epg-ca.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'CA',
            'enabled'  => env('EPG_CA_ENABLED', true),
        ],
        'iptv-epg.org-au' => [
            'name'     => 'AU - iptv-epg.org',
            'url'      => 'https://iptv-epg.org/files/epg-au.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'AU',
            'enabled'  => env('EPG_AU_ENABLED', true),
        ],
        'iptv-epg.org-de' => [
            'name'     => 'DE - iptv-epg.org',
            'url'      => 'https://iptv-epg.org/files/epg-de.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'DE',
            'enabled'  => env('EPG_DE_ENABLED', false),
        ],
        'iptv-epg.org-fr' => [
            'name'     => 'FR - iptv-epg.org',
            'url'      => 'https://iptv-epg.org/files/epg-fr.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'FR',
            'enabled'  => env('EPG_FR_ENABLED', false),
        ],
        'iptv-epg.org-in' => [
            'name'     => 'IN - iptv-epg.org',
            'url'      => 'https://iptv-epg.org/files/epg-in.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'IN',
            'enabled'  => env('EPG_IN_ENABLED', false),
        ],
        'epgshare01-us' => [
            'name'     => 'US - epgshare01',
            'url'      => 'https://epgshare01.online/epgshare01/epg_ripper_US2.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'US',
            'enabled'  => env('EPG_EPGSHARE01_US_ENABLED', false),
        ],
        'epgshare01-uk' => [
            'name'     => 'UK - epgshare01',
            'url'      => 'https://epgshare01.online/epgshare01/epg_ripper_UK1.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'GB',
            'enabled'  => env('EPG_EPGSHARE01_UK_ENABLED', false),
        ],
        'epgshare01-us-locals' => [
            'name'     => 'US Locals - epgshare01',
            'url'      => 'https://epgshare01.online/epgshare01/epg_ripper_US_LOCALS1.xml.gz',
            'type'     => 'xmltv',
            'country'  => 'US',
            'enabled'  => env('EPG_EPGSHARE01_US_LOCALS_ENABLED', false),
        ],
        'epgshare01-bein' => [
            'name'     => 'beIN Sports - epgshare01',
            'url'      => 'https://epgshare01.online/epgshare01/epg_ripper_BEIN1.xml.gz',
            'type'     => 'xmltv',
            'country'  => null,
            'enabled'  => env('EPG_EPGSHARE01_BEIN_ENABLED', false),
        ],
        'epgshare01-aljazeera' => [
            'name'     => 'Al Jazeera - epgshare01',
            'url'      => 'https://epgshare01.online/epgshare01/epg_ripper_ALJAZEERA1.xml.gz',
            'type'     => 'xmltv',
            'country'  => null,
            'enabled'  => env('EPG_EPGSHARE01_ALJAZEERA_ENABLED', false),
        ],
        'mjhnz-pluto' => [
            'name'     => 'PlutoTV - mjh.nz',
            'url'      => 'https://i.mjh.nz/PlutoTV/all.xml.gz',
            'type'     => 'xmltv',
            'country'  => null,
            'enabled'  => env('EPG_MJHNZ_PLUTO_ENABLED', true),
        ],
        'mjhnz-samsung' => [
            'name'     => 'Samsung TV+ - mjh.nz',
            'url'      => 'https://i.mjh.nz/SamsungTVPlus/all.xml.gz',
            'type'     => 'xmltv',
            'country'  => null,
            'enabled'  => env('EPG_MJHNZ_SAMSUNG_ENABLED', true),
        ],
        'mjhnz-plex' => [
            'name'     => 'Plex - mjh.nz',
            'url'      => 'https://i.mjh.nz/Plex/all.xml.gz',
            'type'     => 'xmltv',
            'country'  => null,
            'enabled'  => env('EPG_MJHNZ_PLEX_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Storage
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'driver'    => env('EPG_STORAGE_DRIVER', 'database'),
        'cache_ttl' => env('EPG_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Data Processing
    |--------------------------------------------------------------------------
    */

    'processing' => [
        'timezone'               => env('EPG_TIMEZONE', 'UTC'),
        'max_programs_per_channel' => env('EPG_MAX_PROGRAMS', 500),
        'look_ahead_days'        => env('EPG_LOOK_AHEAD_DAYS', 7),
        'look_back_days'         => env('EPG_LOOK_BACK_DAYS', 1),
        'batch_size'             => env('EPG_BATCH_SIZE', 500),
        'fetch_timeout'          => env('EPG_FETCH_TIMEOUT', 120),
        'fetch_concurrency'      => env('EPG_FETCH_CONCURRENCY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | EPG Output Formats
    |--------------------------------------------------------------------------
    */

    'output' => [
        'xmltv' => [
            'enabled'  => true,
            'encoding' => 'UTF-8',
            'compress' => true,
        ],
        'json' => [
            'enabled' => true,
            'pretty'  => false,
        ],
    ],

];
