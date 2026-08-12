<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->tinyInteger('day_of_week')->notNull();
            $table->time('start_time')->notNull();
            $table->time('end_time')->notNull();
            $table->unsignedBigInteger('playlist_id')->nullable();
            $table->enum('content_type', ['playlist', 'single', 'block'])->default('playlist');
            $table->enum('loop_mode', ['loop', 'once', 'stop'])->default('loop');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('user_channels')->onDelete('cascade');
            $table->foreign('playlist_id')->references('id')->on('channel_playlist_items')->onDelete('set null');
            $table->index(['channel_id', 'day_of_week']);
            $table->index(['start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_schedules');
    }
};