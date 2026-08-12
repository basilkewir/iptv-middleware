<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tmdb_cache', function (Blueprint $table) {
            $table->id();
            $table->string('tmdb_id', 50);
            $table->enum('media_type', ['movie', 'tv', 'person']);
            $table->json('data');
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->decimal('popularity', 10, 2)->nullable();
            $table->decimal('vote_average', 3, 1)->nullable();
            $table->integer('vote_count')->nullable();
            $table->date('release_date')->nullable();
            $table->timestamp('last_updated');
            $table->timestamps();

            $table->unique(['tmdb_id', 'media_type']);
            $table->index('popularity');
            $table->index('release_date');
        });

        Schema::create('tmdb_mapping', function (Blueprint $table) {
            $table->id();
            $table->enum('content_type', ['vod', 'series', 'episode']);
            $table->unsignedBigInteger('content_id');
            $table->string('tmdb_id', 50);
            $table->enum('media_type', ['movie', 'tv', 'person']);
            $table->boolean('is_primary')->default(true);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->timestamp('mapped_at');
            $table->timestamps();

            $table->unique(['content_type', 'content_id', 'tmdb_id']);
            $table->index('tmdb_id');
        });

        Schema::create('tmdb_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 50);
            $table->string('tmdb_id', 50)->nullable();
            $table->string('content_type', 50)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tmdb_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tmdb_sync_logs');
        Schema::dropIfExists('tmdb_mapping');
        Schema::dropIfExists('tmdb_cache');
    }
};
