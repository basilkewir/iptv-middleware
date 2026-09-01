<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'label' => 'Super Admin',
                'description' => 'Full system access to every module.',
                'permissions' => ['full_access', 'user_management', 'role_management', 'my_channels'],
            ],
            [
                'name' => 'admin',
                'label' => 'Admin',
                'description' => 'Full access to the admin panel.',
                'permissions' => ['full_access', 'user_management', 'role_management', 'my_channels'],
            ],
            [
                'name' => 'moderator',
                'label' => 'Moderator',
                'description' => 'Manages content and my channels, no user management.',
                'permissions' => ['my_channels', 'content_management'],
            ],
            [
                'name' => 'support',
                'label' => 'Support',
                'description' => 'View-only access for support staff.',
                'permissions' => ['view_only'],
            ],
            [
                'name' => 'reseller',
                'label' => 'Reseller',
                'description' => 'Reseller account managing their own clients.',
                'permissions' => ['my_channels'],
            ],
            [
                'name' => 'client',
                'label' => 'Client',
                'description' => 'End user with HLS/Xtream access, no admin panel.',
                'permissions' => [],
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}