<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vod_media', function (Blueprint $table) {
            $table->date('air_date')->nullable()->after('episode_title');
            $table->string('still_url')->nullable()->after('air_date');
        });
    }

    public function down(): void
    {
        Schema::table('vod_media', function (Blueprint $table) {
            $table->dropColumn(['air_date', 'still_url']);
        });
    }
};
