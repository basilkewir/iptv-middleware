<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_channel_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->string('session_id', 100)->unique()->nullable();

            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->integer('duration')->default(0);

            $table->json('playlist_snapshot')->nullable();
            $table->unsignedBigInteger('current_item_id')->nullable();
            $table->integer('current_item_position')->default(0);

            $table->integer('total_viewers')->default(0);
            $table->integer('peak_viewers')->default(0);
            $table->integer('total_views')->default(0);
            $table->bigInteger('bandwidth_used')->default(0);

            $table->string('stream_quality', 20)->nullable();
            $table->integer('avg_bitrate')->nullable();

            $table->enum('status', ['starting', 'running', 'ended', 'error'])->default('starting');
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->foreign('current_item_id')->references('id')->on('my_channel_content')->onDelete('set null');
            $table->index(['channel_id', 'status']);
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_channel_broadcasts');
    }
};
