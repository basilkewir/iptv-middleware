<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_view_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('start_time')->notNull();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration')->nullable();
            $table->integer('progress')->nullable();
            $table->string('quality', 20)->nullable();
            $table->integer('bitrate')->nullable();
            $table->string('resolution', 20)->nullable();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('user_channels')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('channel_id');
            $table->index('user_id');
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_view_logs');
    }
};