<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_push_destinations', function (Blueprint $table) {
            $table->unsignedInteger('restart_count')->default(0)->after('last_error');
            $table->timestamp('last_restart_at')->nullable()->after('restart_count');
        });
    }

    public function down(): void
    {
        Schema::table('channel_push_destinations', function (Blueprint $table) {
            $table->dropColumn(['restart_count', 'last_restart_at']);
        });
    }
};
