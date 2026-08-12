<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->enum('content_type', ['vod', 'series', 'episode', 'live', 'url', 'file'])->notNull();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('content_title', 255)->notNull();
            $table->text('content_description')->nullable();
            $table->string('media_url', 500)->notNull();
            $table->string('thumbnail_url', 500)->nullable();
            $table->integer('media_duration')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->integer('order_index')->default(0);
            $table->integer('start_time_offset')->default(0);
            $table->integer('end_time_offset')->default(0);
            $table->integer('transition_duration')->default(2);
            $table->enum('transition_type', ['cut', 'fade', 'slide', 'none'])->default('cut');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->json('day_of_week')->nullable();
            $table->integer('override_duration')->default(0);
            $table->string('override_quality', 20)->nullable();
            $table->integer('override_volume')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('plays')->default(0);
            $table->integer('watch_time')->default(0);
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('user_channels')->onDelete('cascade');
            $table->index('channel_id');
            $table->index('order_index');
            $table->index(['content_type', 'content_id']);
            $table->index(['scheduled_start', 'scheduled_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_playlist_items');
    }
};