<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 9.99,
                'duration_days' => 30,
                'max_connections' => 1,
                'features' => json_encode([
                    '5000+ Live Channels',
                    '10,000+ VOD Titles',
                    'HD Quality (1080p)',
                    '1 Device Connection',
                    'No Catch-up TV',
                    'Email Support',
                ]),
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'price' => 19.99,
                'duration_days' => 30,
                'max_connections' => 3,
                'features' => json_encode([
                    '10,000+ Live Channels',
                    '50,000+ VOD Titles',
                    '4K Ultra HD',
                    '3 Device Connections',
                    '7-Day Catch-up TV',
                    'Priority Support',
                    'Multi-screen Support',
                ]),
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'VIP',
                'slug' => 'vip',
                'price' => 39.99,
                'duration_days' => 30,
                'max_connections' => 5,
                'features' => json_encode([
                    '15,000+ Live Channels',
                    '100,000+ VOD Titles',
                    '4K HDR + Dolby Atmos',
                    '5 Device Connections',
                    '30-Day Catch-up TV',
                    '24/7 VIP Support',
                    'Multi-screen Support',
                    'Early Access to New Content',
                    'Exclusive VIP Events',
                ]),
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('subscription_packages')->insert($packages);
    }
}
