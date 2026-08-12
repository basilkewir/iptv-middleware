<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vod_content_id')->constrained('vod_content')->onDelete('cascade');
            $table->integer('rating')->default(0);
            $table->string('title')->nullable();
            $table->text('review')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'vod_content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reviews');
    }
};
