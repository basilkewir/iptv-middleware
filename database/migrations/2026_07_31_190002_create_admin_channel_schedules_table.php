<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channel_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_channel_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('schedule_type', ['once', 'daily', 'weekly', 'monthly'])->default('once');
            $table->json('schedule_days')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->enum('status', ['scheduled', 'active', 'completed', 'cancelled'])->default('scheduled');
            $table->json('playlist_ids')->nullable();
            $table->json('overlay_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('admin_channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->index(['admin_channel_id', 'start_time']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_schedules');
    }
};