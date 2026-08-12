<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_id')->constrained('streaming_servers')->onDelete('cascade');
            $table->string('stream_key')->unique();
            $table->string('stream_url')->nullable();
            $table->string('stream_type')->default('hls');
            $table->string('status')->default('starting');
            $table->unsignedInteger('current_viewers')->default(0);
            $table->unsignedInteger('total_watch_time')->default(0);
            $table->unsignedInteger('avg_bitrate')->nullable();
            $table->unsignedInteger('avg_fps')->nullable();
            $table->unsignedInteger('avg_latency')->nullable();
            $table->string('codec')->nullable();
            $table->string('resolution')->nullable();
            $table->unsignedInteger('bitrate')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'status']);
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streams');
    }
};