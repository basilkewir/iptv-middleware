<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vod_content', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('year')->nullable();
            $table->string('imdb_id')->nullable()->index();
            $table->integer('tmdb_id')->nullable()->index();
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('poster_url')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->string('trailer_url')->nullable();
            $table->enum('type', ['movie', 'series', 'documentary', 'tv_show', 'anime', 'kids']);
            $table->integer('duration')->nullable();
            $table->string('director')->nullable();
            $table->json('cast')->nullable();
            $table->json('genre')->nullable();
            $table->integer('season_count')->default(0);
            $table->integer('episode_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vod_content');
    }
};
