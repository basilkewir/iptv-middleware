<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update moderator role permissions to include vod_management
        DB::table('roles')
            ->where('name', 'moderator')
            ->update(['permissions' => json_encode(['my_channels', 'vod_management'])]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'moderator')
            ->update(['permissions' => json_encode(['my_channels', 'content_management'])]);
    }
};
