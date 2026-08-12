<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_channel_content', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');

            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration')->nullable();

            $table->string('file_name')->nullable();
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->nullable();
            $table->string('thumbnail_url', 500)->nullable();

            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('last_played_at')->nullable();
            $table->integer('play_count')->default(0);

            $table->enum('quality_level', ['4k', 'fhd', 'hd', 'sd', 'low'])->default('hd');
            $table->integer('resolution_width')->nullable();
            $table->integer('resolution_height')->nullable();
            $table->integer('bitrate')->nullable();
            $table->string('video_codec', 50)->nullable();
            $table->string('audio_codec', 50)->nullable();
            $table->decimal('frame_rate', 5, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('featured_order')->default(0);
            $table->boolean('is_transcoded')->default(false);

            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->index('channel_id');
            $table->index('is_active');
            $table->index('quality_level');
            $table->index('play_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_channel_content');
    }
};
