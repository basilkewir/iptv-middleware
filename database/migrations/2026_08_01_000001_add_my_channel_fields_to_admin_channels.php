<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->boolean('is_my_channel')->default(false)->after('channel_type');
            $table->enum('playlist_type', ['continuous', 'scheduled', 'mixed'])->default('continuous')->after('is_my_channel');
        });
    }

    public function down(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->dropColumn(['is_my_channel', 'playlist_type']);
        });
    }
};
