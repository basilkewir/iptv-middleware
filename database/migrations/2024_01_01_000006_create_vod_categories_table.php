<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vod_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vod_content_id')->constrained('vod_content')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('content_categories')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['vod_content_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vod_categories');
    }
};
