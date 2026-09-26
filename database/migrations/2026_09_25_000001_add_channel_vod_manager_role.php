<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAME = 'channel_vod_manager';

    private const LABEL = 'Channels & VOD Manager';

    private const DESCRIPTION = 'Manages My Channels and VOD (movies & series) only — no user, role, billing or system settings access.';

    private const PERMISSIONS = ['my_channels', 'vod_management'];

    public function up(): void
    {
        $exists = DB::table('roles')->where('name', self::NAME)->exists();

        if (! $exists) {
            DB::table('roles')->insert([
                'name'        => self::NAME,
                'label'       => self::LABEL,
                'description' => self::DESCRIPTION,
                'permissions' => json_encode(self::PERMISSIONS),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return;
        }

        // Already present (e.g. created through the Roles UI) — keep the label
        // and description ours, but never downgrade an operator's permissions.
        $current = DB::table('roles')->where('name', self::NAME)->value('permissions');
        $current = is_string($current) ? json_decode($current, true) : $current;

        if (! is_array($current) || $current === []) {
            DB::table('roles')->where('name', self::NAME)->update([
                'label'       => self::LABEL,
                'description' => self::DESCRIPTION,
                'permissions' => json_encode(self::PERMISSIONS),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', self::NAME)->delete();
    }
};
