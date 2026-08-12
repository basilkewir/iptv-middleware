<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username' => 'admin',
            'email' => 'admin@iptv-middleware.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'username' => 'reseller1',
            'email' => 'reseller@iptv-middleware.com',
            'password' => Hash::make('password'),
            'is_reseller' => true,
            'is_active' => true,
            'credits' => 500,
            'email_verified_at' => now(),
        ]);

        User::create([
            'username' => 'johndoe',
            'email' => 'user@iptv-middleware.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'username' => 'janesmith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'username' => 'bobwilson',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
