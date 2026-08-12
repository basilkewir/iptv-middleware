<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channel_broadcast_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_channel_id');
            $table->enum('event_type', ['broadcast_start', 'broadcast_end', 'broadcast_error', 'stream_restart', 'quality_change', 'overlay_change', 'schedule_change']);
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('stream_url', 500)->nullable();
            $table->string('stream_type', 20)->nullable();
            $table->string('quality', 20)->nullable();
            $table->integer('viewers')->default(0);
            $table->integer('duration_seconds')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('broadcast_start')->nullable();
            $table->timestamp('broadcast_end')->nullable();
            $table->timestamps();

            $table->foreign('admin_channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->index(['admin_channel_id', 'event_type']);
            $table->index('broadcast_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_broadcast_logs');
    }
};