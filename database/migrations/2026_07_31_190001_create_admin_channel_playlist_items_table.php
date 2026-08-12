<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channel_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_channel_id');
            $table->string('content_type', 50)->default('stream');
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('content_title', 255)->nullable();
            $table->text('content_description')->nullable();
            $table->string('media_url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->integer('media_duration')->default(0);
            $table->bigInteger('file_size')->default(0);
            $table->integer('order_index')->default(0);
            $table->integer('start_time_offset')->default(0);
            $table->integer('end_time_offset')->default(0);
            $table->integer('transition_duration')->default(0);
            $table->string('transition_type', 20)->default('cut');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('admin_channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->index(['admin_channel_id', 'order_index']);
            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_playlist_items');
    }
};