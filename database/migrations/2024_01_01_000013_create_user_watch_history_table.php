<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('vod_content_id')->nullable()->constrained('vod_content')->onDelete('cascade');
            $table->foreignId('media_id')->nullable()->constrained('vod_media')->onDelete('set null');
            $table->decimal('progress', 5, 2)->default(0);
            $table->integer('duration_watched')->default(0);
            $table->timestamp('watched_at')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'channel_id', 'vod_content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_watch_history');
    }
};
