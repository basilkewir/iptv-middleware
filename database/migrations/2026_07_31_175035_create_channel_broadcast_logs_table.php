<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_broadcast_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->string('broadcast_id', 100)->nullable();
            $table->timestamp('start_time')->notNull();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration')->nullable();
            $table->enum('content_type', ['playlist', 'single', 'schedule'])->notNull();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->integer('viewers')->default(0);
            $table->integer('peak_viewers')->default(0);
            $table->bigInteger('bandwidth_used')->default(0);
            $table->enum('status', ['scheduled', 'started', 'running', 'ended', 'error'])->default('scheduled');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('user_channels')->onDelete('cascade');
            $table->index(['channel_id', 'start_time']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_broadcast_logs');
    }
};