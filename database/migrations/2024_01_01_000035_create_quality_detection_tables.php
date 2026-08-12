<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // quality_detection_settings - singleton row for global config
        Schema::create('quality_detection_settings', function (Blueprint $table) {
            $table->id();
            $table->string('detection_method')->default('combined'); // resolution, bitrate, combined, ai
            $table->integer('resolution_4k_min')->default(3840);
            $table->integer('resolution_fhd_min')->default(1920);
            $table->integer('resolution_hd_min')->default(1280);
            $table->integer('resolution_sd_min')->default(640);
            $table->integer('bitrate_4k_min')->default(20000);
            $table->integer('bitrate_fhd_min')->default(8000);
            $table->integer('bitrate_hd_min')->default(4500);
            $table->integer('bitrate_sd_min')->default(1000);
            $table->boolean('auto_scan_enabled')->default(true);
            $table->integer('scan_interval')->default(86400);
            $table->integer('max_concurrent_scans')->default(10);
            $table->integer('scan_timeout')->default(30);
            $table->boolean('notify_on_change')->default(true);
            // Badge display settings
            $table->boolean('show_badge_channels')->default(true);
            $table->boolean('show_badge_epg')->default(true);
            $table->boolean('show_badge_player')->default(true);
            $table->boolean('show_badge_channel_list')->default(true);
            $table->string('badge_style')->default('modern'); // classic, modern, minimal, fluent
            // Auto-update settings
            $table->boolean('auto_update_new')->default(true);
            $table->boolean('auto_update_existing')->default(true);
            $table->string('update_interval')->default('daily');
            // VOD-specific settings
            $table->boolean('vod_detection_enabled')->default(true);
            $table->boolean('detect_file_metadata')->default(true);
            $table->boolean('detect_stream_analysis')->default(true);
            $table->boolean('detect_ffprobe')->default(true);
            $table->boolean('detect_ai_based')->default(false);
            $table->boolean('detect_new_uploads')->default(true);
            $table->boolean('detect_existing_files')->default(true);
            $table->boolean('detect_series')->default(true);
            $table->boolean('detect_imported')->default(true);
            $table->boolean('detect_multi_quality')->default(true);
            $table->boolean('auto_select_best')->default(true);
            $table->boolean('allow_manual_override')->default(true);
            $table->boolean('transcode_lower_qualities')->default(false);
            $table->boolean('show_vod_badge_thumbnail')->default(true);
            $table->boolean('show_vod_badge_details')->default(true);
            $table->boolean('show_vod_badge_player')->default(true);
            $table->boolean('show_vod_quality_options')->default(true);
            $table->boolean('auto_select_best_device')->default(true);
            $table->string('vod_badge_position')->default('top-right');
            $table->timestamps();
        });

        // channel_quality_cache
        Schema::create('channel_quality_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('quality_level', 10); // 4k, fhd, hd, sd, low
            $table->integer('resolution_width')->nullable();
            $table->integer('resolution_height')->nullable();
            $table->integer('bitrate')->nullable();
            $table->string('video_codec', 50)->nullable();
            $table->string('audio_codec', 50)->nullable();
            $table->decimal('frame_rate', 10, 2)->nullable();
            $table->timestamp('scan_timestamp')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('verified_by', 100)->nullable();
            $table->unique('channel_id');
        });

        // vod_quality_cache
        Schema::create('vod_quality_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vod_media_id')->constrained('vod_media')->onDelete('cascade');
            $table->string('quality_level', 10); // 4k, fhd, hd, sd, low, mobile
            $table->integer('resolution_width')->nullable();
            $table->integer('resolution_height')->nullable();
            $table->integer('bitrate')->nullable();
            $table->string('video_codec', 50)->nullable();
            $table->string('audio_codec', 50)->nullable();
            $table->decimal('frame_rate', 10, 2)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->timestamp('scan_timestamp')->nullable();
            $table->boolean('is_transcoded')->default(false);
            $table->string('source_quality', 10)->nullable();
            $table->unique('vod_media_id');
        });

        // quality_detection_logs
        Schema::create('quality_detection_logs', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 10); // channel, vod
            $table->bigInteger('content_id');
            $table->string('detected_quality', 20)->nullable();
            $table->string('detection_method', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 15)->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['content_type', 'content_id', 'status']);
        });

        // Add quality columns to channels table
        Schema::table('channels', function (Blueprint $table) {
            $table->string('quality_level', 10)->nullable()->after('stream_url');
            $table->string('quality_badge', 20)->nullable()->after('quality_level');
            $table->timestamp('quality_updated_at')->nullable()->after('quality_badge');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['quality_level', 'quality_badge', 'quality_updated_at']);
        });
        Schema::dropIfExists('quality_detection_logs');
        Schema::dropIfExists('vod_quality_cache');
        Schema::dropIfExists('channel_quality_cache');
        Schema::dropIfExists('quality_detection_settings');
    }
};
