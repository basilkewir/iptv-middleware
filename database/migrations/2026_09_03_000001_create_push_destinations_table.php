<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('protocol', ['rtmp', 'srt']);
            $table->string('url');
            $table->string('stream_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_push_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('push_destination_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['idle', 'pushing', 'failed'])->default('idle');
            $table->unsignedBigInteger('ffmpeg_pid')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'push_destination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_push_destinations');
        Schema::dropIfExists('push_destinations');
    }
};
