<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'app_name',
                'value' => 'IPTV Middleware',
                'group' => 'general',
                'type' => 'string',
                'description' => 'Application name',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_description',
                'value' => 'Premium IPTV Streaming Middleware',
                'group' => 'general',
                'type' => 'string',
                'description' => 'Application description',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_url',
                'value' => env('APP_URL', 'http://localhost'),
                'group' => 'general',
                'type' => 'string',
                'description' => 'Application URL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'admin_email',
                'value' => 'admin@iptv-middleware.com',
                'group' => 'general',
                'type' => 'string',
                'description' => 'Administrator email address',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'timezone',
                'value' => 'UTC',
                'group' => 'general',
                'type' => 'string',
                'description' => 'System timezone',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_language',
                'value' => 'en',
                'group' => 'general',
                'type' => 'string',
                'description' => 'Default language code',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'max_concurrent_streams',
                'value' => '5',
                'group' => 'streaming',
                'type' => 'integer',
                'description' => 'Maximum concurrent streams per user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'stream_buffer_size',
                'value' => '10240',
                'group' => 'streaming',
                'type' => 'integer',
                'description' => 'Stream buffer size in KB',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'enable_epg',
                'value' => 'true',
                'group' => 'streaming',
                'type' => 'boolean',
                'description' => 'Enable Electronic Program Guide',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'epg_url',
                'value' => 'http://xmltv.example.com/epg.xml',
                'group' => 'streaming',
                'type' => 'string',
                'description' => 'EPG XML feed URL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'enable_vod',
                'value' => 'true',
                'group' => 'content',
                'type' => 'boolean',
                'description' => 'Enable Video on Demand',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'enable_recording',
                'value' => 'false',
                'group' => 'content',
                'type' => 'boolean',
                'description' => 'Enable cloud DVR recording',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'registration_enabled',
                'value' => 'true',
                'group' => 'auth',
                'type' => 'boolean',
                'description' => 'Allow new user registration',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'require_email_verification',
                'value' => 'true',
                'group' => 'auth',
                'type' => 'boolean',
                'description' => 'Require email verification for new users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'trial_days',
                'value' => '7',
                'group' => 'billing',
                'type' => 'integer',
                'description' => 'Free trial period in days',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'trial_enabled',
                'value' => 'true',
                'group' => 'billing',
                'type' => 'boolean',
                'description' => 'Enable free trial for new users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'group' => 'system',
                'type' => 'boolean',
                'description' => 'Enable maintenance mode',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'log_retention_days',
                'value' => '30',
                'group' => 'system',
                'type' => 'integer',
                'description' => 'Number of days to retain activity logs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('system_settings')->insert($settings);
    }
}
