<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vod_seasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vod_content_id');
            $table->integer('season_number');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->year('season_year')->nullable();
            $table->integer('episode_count')->default(0);
            $table->integer('total_duration')->default(0);
            $table->boolean('is_available')->default(true);
            $table->date('air_date')->nullable();
            $table->timestamps();

            $table->foreign('vod_content_id')->references('id')->on('vod_content')->onDelete('cascade');
            $table->unique(['vod_content_id', 'season_number'], 'unique_season');
            $table->index('season_number');
        });

        Schema::create('vod_episodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->integer('episode_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('stream_url')->nullable();
            $table->enum('quality', ['4k', 'fhd', 'hd', 'sd', 'low', 'mobile'])->default('hd');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_path')->nullable();
            $table->date('air_date')->nullable();
            $table->json('guest_stars')->nullable();
            $table->string('director')->nullable();
            $table->string('writer')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->string('tmdb_id', 50)->nullable();
            $table->json('tmdb_data')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('views')->default(0);
            $table->integer('watch_time')->default(0);
            $table->timestamps();

            $table->foreign('season_id')->references('id')->on('vod_seasons')->onDelete('cascade');
            $table->unique(['season_id', 'episode_number'], 'unique_episode');
            $table->index('episode_number');
            $table->index('air_date');
        });

        Schema::create('vod_persons', function (Blueprint $table) {
            $table->id();
            $table->string('tmdb_id', 50)->nullable();
            $table->string('name');
            $table->string('profile_url')->nullable();
            $table->text('biography')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('death_date')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('known_for_department')->nullable();
            $table->decimal('popularity', 10, 2)->default(0);
            $table->timestamps();

            $table->unique('tmdb_id', 'unique_tmdb_person');
            $table->index('name');
        });

        Schema::create('vod_casts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vod_content_id');
            $table->unsignedBigInteger('person_id');
            $table->string('character_name')->nullable();
            $table->enum('role', ['main', 'supporting', 'cameo', 'guest'])->default('supporting');
            $table->integer('order_index')->default(0);
            $table->timestamp('created_at')->default(now());

            $table->foreign('vod_content_id')->references('id')->on('vod_content')->onDelete('cascade');
            $table->foreign('person_id')->references('id')->on('vod_persons')->onDelete('cascade');
            $table->index('vod_content_id');
        });

        Schema::create('vod_crews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vod_content_id');
            $table->unsignedBigInteger('person_id');
            $table->string('job')->nullable();
            $table->string('department')->nullable();
            $table->timestamp('created_at')->default(now());

            $table->foreign('vod_content_id')->references('id')->on('vod_content')->onDelete('cascade');
            $table->foreign('person_id')->references('id')->on('vod_persons')->onDelete('cascade');
            $table->index('vod_content_id');
            $table->index('job');
        });

        Schema::create('vod_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vod_content_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('rating')->default(1);
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('spoiler')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->integer('likes')->default(0);
            $table->boolean('reported')->default(false);
            $table->timestamps();

            $table->foreign('vod_content_id')->references('id')->on('vod_content')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['vod_content_id', 'user_id'], 'unique_review');
            $table->index('rating');
            $table->index('is_approved');
        });

        Schema::create('vod_watchlists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vod_content_id');
            $table->integer('favorite_order')->default(0);
            $table->timestamp('created_at')->default(now());

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('vod_content_id')->references('id')->on('vod_content')->onDelete('cascade');
            $table->unique(['user_id', 'vod_content_id'], 'unique_watchlist');
            $table->index('user_id');
        });

        Schema::create('vod_watch_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vod_content_id');
            $table->unsignedBigInteger('episode_id')->nullable();
            $table->integer('progress')->default(0);
            $table->integer('watch_duration')->default(0);
            $table->timestamp('last_watched')->useCurrent();
            $table->integer('watch_count')->default(1);
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('vod_content_id')->references('id')->on('vod_content')->onDelete('cascade');
            $table->foreign('episode_id')->references('id')->on('vod_episodes')->onDelete('cascade');
            $table->index(['user_id', 'vod_content_id'], 'idx_user_content');
            $table->index('last_watched');
            $table->index('progress');
        });

        Schema::create('vod_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vod_content_id');
            $table->integer('favorite_order')->default(0);
            $table->timestamp('created_at')->default(now());

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('vod_content_id')->references('id')->on('vod_content')->onDelete('cascade');
            $table->unique(['user_id', 'vod_content_id'], 'unique_favorite');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vod_favorites');
        Schema::dropIfExists('vod_watch_history');
        Schema::dropIfExists('vod_watchlists');
        Schema::dropIfExists('vod_reviews');
        Schema::dropIfExists('vod_crews');
        Schema::dropIfExists('vod_casts');
        Schema::dropIfExists('vod_persons');
        Schema::dropIfExists('vod_episodes');
        Schema::dropIfExists('vod_seasons');
    }
};
