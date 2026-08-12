<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vod_content', function (Blueprint $table) {
            $table->string('stream_url')->nullable()->after('trailer_url');
            $table->string('stream_id')->nullable()->after('stream_url');
            $table->string('stream_icon')->nullable()->after('stream_id');
            $table->string('quality_level')->nullable()->after('stream_icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vod_content', function (Blueprint $table) {
            $table->dropColumn(['stream_url', 'stream_id', 'stream_icon', 'quality_level']);
        });
    }
};
