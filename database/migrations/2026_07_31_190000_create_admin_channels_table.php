<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name');
            $table->string('channel_slug')->unique();
            $table->string('channel_number')->nullable();
            $table->enum('channel_type', ['admin', 'user', 'system', 'custom'])->default('admin');
            $table->text('description')->nullable();

            // Branding
            $table->string('logo_url', 500)->nullable();
            $table->string('banner_url', 500)->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('text_color', 7)->nullable();
            $table->string('watermark_url', 500)->nullable();

            // Stream Settings
            $table->string('stream_url', 500)->nullable();
            $table->enum('stream_type', ['hls', 'rtmp', 'mpegts', 'http', 'srt'])->default('hls');
            $table->string('stream_key', 100)->nullable();
            $table->string('output_resolution', 20)->nullable();
            $table->integer('output_bitrate')->nullable();
            $table->decimal('output_frame_rate', 5, 2)->nullable();
            $table->string('video_codec', 50)->nullable();
            $table->string('audio_codec', 50)->nullable();

            // Broadcast Settings
            $table->enum('broadcast_status', ['offline', 'ready', 'scheduled', 'live', 'ended', 'error'])->default('offline');
            $table->enum('broadcast_mode', ['manual', 'auto', 'scheduled'])->default('manual');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('last_broadcast')->nullable();
            $table->string('timezone', 50)->default('UTC');
            $table->enum('duration_type', ['continuous', 'limited', 'specific'])->default('continuous');

            // Playout Settings
            $table->enum('playout_mode', ['playlist', 'schedule', 'live_input', 'mixed'])->default('playlist');
            $table->integer('default_duration')->default(0);
            $table->boolean('loop_playlist')->default(true);
            $table->boolean('shuffle_mode')->default(false);
            $table->enum('transition_type', ['cut', 'fade', 'slide', 'dissolve'])->default('cut');
            $table->integer('transition_duration')->default(2);

            // Ticker/Overlay Settings
            $table->boolean('enable_ticker')->default(false);
            $table->text('ticker_text')->nullable();
            $table->integer('ticker_speed')->default(30);
            $table->string('ticker_color', 9)->nullable();
            $table->string('ticker_background', 9)->nullable();
            $table->enum('ticker_direction', ['left', 'right', 'up', 'down'])->default('left');
            $table->boolean('enable_overlay_logo')->default(false);
            $table->string('overlay_logo_position', 20)->default('top-left');
            $table->integer('overlay_logo_size')->default(100);
            $table->decimal('overlay_logo_opacity', 3, 2)->default(1.00);
            $table->boolean('enable_overlay_clock')->default(false);
            $table->string('overlay_clock_position', 20)->default('top-right');
            $table->string('overlay_clock_format', 20)->default('HH:MM:SS');
            $table->boolean('enable_watermark')->default(false);
            $table->string('watermark_position', 20)->default('bottom-right');
            $table->decimal('watermark_opacity', 3, 2)->default(0.50);

            // Content Rights
            $table->string('content_owner', 255)->nullable();
            $table->enum('license_type', ['free', 'premium', 'subscription', 'pay_per_view', 'restricted'])->default('free');
            $table->date('license_expiry')->nullable();
            $table->json('content_restrictions')->nullable();
            $table->json('region_restrictions')->nullable();

            // Access Control
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_adult')->default(false);
            $table->integer('featured_order')->default(0);
            $table->boolean('require_subscription')->default(false);
            $table->unsignedBigInteger('subscription_package_id')->nullable();

            // Meta Data
            $table->string('genre', 100)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('language', 10)->default('en');
            $table->string('country', 100)->nullable();
            $table->json('tags')->nullable();

            // Statistics
            $table->bigInteger('total_views')->default(0);
            $table->bigInteger('total_watch_time')->default(0);
            $table->integer('total_subscribers')->default(0);
            $table->integer('peak_viewers')->default(0);
            $table->integer('total_favorites')->default(0);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            // Timestamps
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->notNull();

            $table->foreign('subscription_package_id')->references('id')->on('subscription_packages')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('channel_slug');
            $table->index('channel_type');
            $table->index(['is_active', 'broadcast_status']);
            $table->index('subscription_package_id');
            $table->index(['is_featured', 'featured_order']);
            $table->index('genre');
            $table->index('category');
            $table->index('language');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channels');
    }
};
