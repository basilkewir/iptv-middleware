<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('channel_number');
            $table->string('epg_channel_id')->nullable()->index();
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->string('stream_url');
            $table->enum('stream_type', ['m3u8', 'rtmp', 'rtsp', 'udp', 'hls', 'dash']);
            $table->string('quality', 10)->default('1080p');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_free')->default(false);
            $table->boolean('is_favourite')->default(false);
            $table->string('epg_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('allowed_ips')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
