<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vod_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vod_content_id')->constrained('vod_content')->onDelete('cascade');
            $table->integer('season_number')->nullable();
            $table->integer('episode_number')->nullable();
            $table->string('episode_title')->nullable();
            $table->string('stream_url');
            $table->enum('stream_type', ['hls', 'mp4', 'mkv', 'avi'])->default('hls');
            $table->enum('quality', ['240p', '360p', '480p', '720p', '1080p', '4k'])->default('1080p');
            $table->string('resolution')->nullable();
            $table->string('codec')->nullable();
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->integer('bitrate')->nullable();
            $table->integer('duration')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->index(['vod_content_id', 'season_number', 'episode_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vod_media');
    }
};
