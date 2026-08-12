<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channel_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_channel_id');
            $table->date('date');
            $table->integer('views')->default(0);
            $table->integer('unique_viewers')->default(0);
            $table->integer('total_watch_time_seconds')->default(0);
            $table->integer('peak_concurrent_viewers')->default(0);
            $table->integer('average_watch_duration_seconds')->default(0);
            $table->integer('new_subscribers')->default(0);
            $table->integer('lost_subscribers')->default(0);
            $table->integer('total_subscribers')->default(0);
            $table->integer('buffering_events')->default(0);
            $table->integer('error_events')->default(0);
            $table->decimal('average_bitrate', 10, 2)->default(0);
            $table->json('geo_data')->nullable();
            $table->json('device_data')->nullable();
            $table->json('quality_distribution')->nullable();
            $table->timestamps();

            $table->foreign('admin_channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->unique(['admin_channel_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_analytics');
    }
};