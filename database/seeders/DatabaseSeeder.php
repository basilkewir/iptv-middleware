<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SystemSettingsSeeder::class,
            UserSeeder::class,
            ContentCategorySeeder::class,
            SubscriptionPackageSeeder::class,
            StreamingServerSeeder::class,
            ChannelSeeder::class,
            VODContentSeeder::class,
        ]);
    }
}
