<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_push_destinations', function (Blueprint $table) {
            $table->string('stream_key')->nullable()->after('push_destination_id');
            $table->unsignedInteger('video_bitrate')->nullable()->after('stream_key')->comment('kbps');
            $table->unsignedInteger('audio_bitrate')->nullable()->after('video_bitrate')->comment('kbps');
        });
    }

    public function down(): void
    {
        Schema::table('channel_push_destinations', function (Blueprint $table) {
            $table->dropColumn(['stream_key', 'video_bitrate', 'audio_bitrate']);
        });
    }
};
