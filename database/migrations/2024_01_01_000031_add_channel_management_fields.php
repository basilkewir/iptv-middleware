<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('country', 10)->nullable()->after('stream_type');
            $table->string('language', 10)->nullable()->after('country');
            $table->string('backup_url_1')->nullable()->after('stream_url');
            $table->string('backup_url_2')->nullable()->after('backup_url_1');
            $table->integer('bitrate')->nullable()->after('quality');
            $table->foreignId('epg_source_id')->nullable()->constrained('epg_sources')->nullOnDelete()->after('epg_id');
            $table->string('epg_language', 10)->nullable()->after('epg_source_id');
            $table->string('timezone_offset', 10)->nullable()->after('epg_language');
            $table->boolean('is_adult')->default(false)->after('is_free');
            $table->boolean('transcoding_enabled')->default(false)->after('is_adult');
            $table->string('transcoding_profile')->nullable()->after('transcoding_enabled');
            $table->string('transcoding_resolution')->nullable()->after('transcoding_profile');
            $table->string('transcoding_video_codec')->nullable()->after('transcoding_resolution');
            $table->string('transcoding_audio_codec')->nullable()->after('transcoding_video_codec');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropForeign(['epg_source_id']);
            $table->dropColumn([
                'country', 'language', 'backup_url_1', 'backup_url_2',
                'bitrate', 'epg_source_id', 'epg_language', 'timezone_offset',
                'is_adult', 'transcoding_enabled', 'transcoding_profile',
                'transcoding_resolution', 'transcoding_video_codec', 'transcoding_audio_codec',
            ]);
        });
    }
};
