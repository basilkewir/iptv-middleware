<?php

return [
    /*
    |--------------------------------------------------------------------------
    | License System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Hotel IPTV License System
    | Integrated with kewirdev license system
    */

    // JWT Token Configuration
    'jwt_secret' => env('LICENSE_JWT_SECRET'),
    'token_expiration' => env('LICENSE_TOKEN_EXPIRATION', 3600), // 1 hour in seconds
    'token_refresh_threshold' => env('LICENSE_TOKEN_REFRESH_THRESHOLD', 300), // 5 minutes before expiration

    // Security Configuration
    'enable_device_binding' => env('LICENSE_DEVICE_BINDING', true),
    'enable_ip_validation' => env('LICENSE_IP_VALIDATION', false),
    'enable_signature_validation' => env('LICENSE_SIGNATURE_VALIDATION', false),
    'max_validation_attempts' => env('LICENSE_MAX_VALIDATION_ATTEMPTS', 10),
    'validation_rate_limit_window' => env('LICENSE_RATE_LIMIT_WINDOW', 60), // minutes

    // Geo-location restrictions
    'allowed_countries' => env('LICENSE_ALLOWED_COUNTRIES') ? 
        explode(',', env('LICENSE_ALLOWED_COUNTRIES')) : [],

    // License Types Configuration
    'license_types' => [
        'trial' => [
            'name' => 'Trial License',
            'duration_days' => 30,
            'max_devices' => 1,
            'features' => [
                'max_channels' => 10,
                'max_users' => 5,
                'analytics' => false,
                'custom_branding' => false,
                'api_access' => false,
                'support_level' => 'basic'
            ]
        ],
        'basic' => [
            'name' => 'Basic License',
            'duration_days' => 365,
            'max_devices' => 3,
            'features' => [
                'max_channels' => 50,
                'max_users' => 25,
                'analytics' => true,
                'custom_branding' => false,
                'api_access' => false,
                'support_level' => 'standard'
            ]
        ],
        'premium' => [
            'name' => 'Premium License',
            'duration_days' => 365,
            'max_devices' => 10,
            'features' => [
                'max_channels' => 200,
                'max_users' => 100,
                'analytics' => true,
                'custom_branding' => true,
                'api_access' => true,
                'support_level' => 'premium'
            ]
        ],
        'enterprise' => [
            'name' => 'Enterprise License',
            'duration_days' => 365,
            'max_devices' => 50,
            'features' => [
                'max_channels' => -1, // unlimited
                'max_users' => -1, // unlimited
                'analytics' => true,
                'custom_branding' => true,
                'api_access' => true,
                'support_level' => 'enterprise',
                'white_label' => true,
                'custom_integrations' => true
            ]
        ],
        'perpetual' => [
            'name' => 'Perpetual License',
            'duration_days' => null, // never expires
            'max_devices' => 1000,
            'features' => [
                'max_channels' => -1, // unlimited
                'max_users' => -1, // unlimited
                'analytics' => true,
                'custom_branding' => true,
                'api_access' => true,
                'support_level' => 'priority',
                'white_label' => true,
                'custom_integrations' => true,
                'lifetime_updates' => true,
                'priority_support' => true,
                'custom_development' => true
            ]
        ]
    ],

    // Device Types Configuration
    'device_types' => [
        'android_tv' => [
            'name' => 'Android TV',
            'requires_device_binding' => true,
            'heartbeat_interval' => 300, // 5 minutes
            'offline_grace_period' => 3600, // 1 hour
        ],
        'smart_tv' => [
            'name' => 'Smart TV',
            'requires_device_binding' => true,
            'heartbeat_interval' => 300, // 5 minutes
            'offline_grace_period' => 3600, // 1 hour
        ],
        'management_backend' => [
            'name' => 'Management Backend',
            'requires_device_binding' => false,
            'heartbeat_interval' => 60, // 1 minute
            'offline_grace_period' => 300, // 5 minutes
        ],
        'admin_panel' => [
            'name' => 'Admin Panel',
            'requires_device_binding' => false,
            'heartbeat_interval' => 60, // 1 minute
            'offline_grace_period' => 300, // 5 minutes
        ]
    ],

    // Validation Configuration
    'validation' => [
        'cache_duration' => 300, // 5 minutes
        'offline_validation_duration' => 86400, // 24 hours
        'require_periodic_validation' => true,
        'periodic_validation_interval' => 3600, // 1 hour
    ],

    // Security Features
    'security' => [
        'detect_vm' => env('LICENSE_DETECT_VM', true),
        'detect_debugger' => env('LICENSE_DETECT_DEBUGGER', true),
        'detect_root' => env('LICENSE_DETECT_ROOT', true),
        'block_emulators' => env('LICENSE_BLOCK_EMULATORS', true),
        'require_ssl' => env('LICENSE_REQUIRE_SSL', true),
    ],

    // Monitoring and Analytics
    'monitoring' => [
        'log_all_validations' => env('LICENSE_LOG_ALL_VALIDATIONS', true),
        'log_failed_attempts' => env('LICENSE_LOG_FAILED_ATTEMPTS', true),
        'alert_on_suspicious_activity' => env('LICENSE_ALERT_SUSPICIOUS_ACTIVITY', true),
        'retention_days' => env('LICENSE_LOG_RETENTION_DAYS', 90),
    ],

    // API Configuration
    'api' => [
        'base_url' => env('LICENSE_API_BASE_URL', 'https://kewirdev.com/api/license'),
        'timeout' => env('LICENSE_API_TIMEOUT', 30),
        'retry_attempts' => env('LICENSE_API_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('LICENSE_API_RETRY_DELAY', 1000), // milliseconds
        'secret' => env('KEWIRDEV_API_SECRET', env('LICENSE_JWT_SECRET')),
    ],

    // Notification Configuration
    'notifications' => [
        'license_expiry_warning_days' => [30, 7, 1],
        'device_limit_warning_threshold' => 0.8, // 80% of limit
        'suspicious_activity_threshold' => 5, // failed attempts
        'notify_admin_email' => env('LICENSE_ADMIN_EMAIL'),
    ],

    // Feature Flags
    'features' => [
        'enable_offline_mode' => env('LICENSE_ENABLE_OFFLINE_MODE', true),
        'enable_device_transfer' => env('LICENSE_ENABLE_DEVICE_TRANSFER', false),
        'enable_license_sharing' => env('LICENSE_ENABLE_LICENSE_SHARING', false),
        'enable_usage_analytics' => env('LICENSE_ENABLE_USAGE_ANALYTICS', true),
    ],

    // Development/Testing Configuration
    'development' => [
        'bypass_validation' => env('LICENSE_BYPASS_VALIDATION', false),
        'mock_responses' => env('LICENSE_MOCK_RESPONSES', false),
        'debug_mode' => env('LICENSE_DEBUG_MODE', false),
    ],
];