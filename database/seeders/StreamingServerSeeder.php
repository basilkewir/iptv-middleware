<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StreamingServerSeeder extends Seeder
{
    public function run(): void
    {
        $servers = [
            [
                'name' => 'US East Server',
                'host' => 'stream-us-east.iptv-middleware.com',
                'port' => 1935,
                'is_active' => true,
                'max_connections' => 5000,
                'current_connections' => 0,
                'bandwidth' => 10000,
                'location' => 'New York, USA',
                'settings' => json_encode([
                    'rtmp_port' => 1935,
                    'http_port' => 8080,
                    'hls_port' => 8081,
                    'ssl_enabled' => true,
                    'ssl_port' => 443,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'US West Server',
                'host' => 'stream-us-west.iptv-middleware.com',
                'port' => 1935,
                'is_active' => true,
                'max_connections' => 5000,
                'current_connections' => 0,
                'bandwidth' => 10000,
                'location' => 'Los Angeles, USA',
                'settings' => json_encode([
                    'rtmp_port' => 1935,
                    'http_port' => 8080,
                    'hls_port' => 8081,
                    'ssl_enabled' => true,
                    'ssl_port' => 443,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Europe Server',
                'host' => 'stream-europe.iptv-middleware.com',
                'port' => 1935,
                'is_active' => true,
                'max_connections' => 4000,
                'current_connections' => 0,
                'bandwidth' => 8000,
                'location' => 'London, UK',
                'settings' => json_encode([
                    'rtmp_port' => 1935,
                    'http_port' => 8080,
                    'hls_port' => 8081,
                    'ssl_enabled' => true,
                    'ssl_port' => 443,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Asia Server',
                'host' => 'stream-asia.iptv-middleware.com',
                'port' => 1935,
                'is_active' => true,
                'max_connections' => 3000,
                'current_connections' => 0,
                'bandwidth' => 6000,
                'location' => 'Singapore',
                'settings' => json_encode([
                    'rtmp_port' => 1935,
                    'http_port' => 8080,
                    'hls_port' => 8081,
                    'ssl_enabled' => true,
                    'ssl_port' => 443,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('streaming_servers')->insert($servers);
    }
}
