<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vod_content_id')->nullable()->constrained('vod_content')->onDelete('cascade');
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('favorite_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'vod_content_id', 'channel_id'], 'user_favorites_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
    }
};
