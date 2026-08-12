<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('channel_name');
            $table->string('channel_slug')->unique();
            $table->text('description')->nullable();
            $table->string('channel_number', 50)->nullable();

            $table->string('logo_url', 500)->nullable();
            $table->string('banner_url', 500)->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('text_color', 7)->nullable();

            $table->string('stream_url', 500)->nullable();
            $table->enum('stream_type', ['hls', 'rtmp', 'mpegts', 'http'])->default('hls');
            $table->string('stream_key', 100)->nullable();
            $table->string('output_resolution', 20)->nullable();
            $table->integer('output_bitrate')->nullable();

            $table->enum('playlist_mode', ['auto', 'manual', 'scheduled'])->default('auto');
            $table->integer('default_duration')->default(0);
            $table->boolean('loop_playlist')->default(true);
            $table->boolean('shuffle_mode')->default(false);

            $table->boolean('is_live')->default(false);
            $table->enum('broadcast_status', ['offline', 'scheduled', 'live', 'ended'])->default('offline');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->string('timezone', 50)->default('UTC');

            $table->boolean('enable_ticker')->default(false);
            $table->text('ticker_text')->nullable();
            $table->integer('ticker_speed')->default(30);
            $table->string('ticker_color', 7)->nullable();
            $table->string('ticker_background', 7)->nullable();
            $table->boolean('enable_overlay_logo')->default(false);
            $table->string('overlay_logo_position', 20)->default('top-left');
            $table->integer('overlay_logo_size')->default(100);

            $table->string('language', 10)->default('en');
            $table->string('genre', 100)->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('is_adult')->default(false);
            $table->boolean('is_featured')->default(false);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('approved')->default(false);
            $table->timestamp('approved_at')->nullable();

            $table->integer('views')->default(0);
            $table->integer('watch_time')->default(0);
            $table->integer('favorites')->default(0);

            $table->timestamps();
            $table->timestamp('last_broadcast')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('is_active');
            $table->index('channel_slug');
            $table->index('approved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_channels');
    }
};