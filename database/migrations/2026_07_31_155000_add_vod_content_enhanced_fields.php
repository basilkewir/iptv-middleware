<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vod_content', function (Blueprint $table) {
            if (!Schema::hasColumn('vod_content', 'original_title')) {
                $table->string('original_title')->nullable()->after('title');
            }
            if (!Schema::hasColumn('vod_content', 'country')) {
                $table->string('country', 100)->nullable()->after('director');
            }
            if (!Schema::hasColumn('vod_content', 'language')) {
                $table->string('language', 50)->nullable()->after('country');
            }
            if (!Schema::hasColumn('vod_content', 'age_rating')) {
                $table->string('age_rating', 10)->nullable()->after('rating');
            }
            if (!Schema::hasColumn('vod_content', 'banner_url')) {
                $table->string('banner_url')->nullable()->after('backdrop_url');
            }
            if (!Schema::hasColumn('vod_content', 'thumbnail_url')) {
                $table->string('thumbnail_url')->nullable()->after('banner_url');
            }
            if (!Schema::hasColumn('vod_content', 'tmdb_data')) {
                $table->json('tmdb_data')->nullable()->after('tmdb_id');
            }
            if (!Schema::hasColumn('vod_content', 'quality_badge')) {
                $table->string('quality_badge')->nullable()->after('quality_level');
            }
            if (!Schema::hasColumn('vod_content', 'quality_updated_at')) {
                $table->timestamp('quality_updated_at')->nullable()->after('quality_badge');
            }
            if (!Schema::hasColumn('vod_content', 'is_adult')) {
                $table->boolean('is_adult')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('vod_content', 'is_available')) {
                $table->boolean('is_available')->default(true)->after('is_adult');
            }
            if (!Schema::hasColumn('vod_content', 'featured_order')) {
                $table->integer('featured_order')->default(0)->after('is_available');
            }
            if (!Schema::hasColumn('vod_content', 'views')) {
                $table->integer('views')->default(0)->after('view_count');
            }
            if (!Schema::hasColumn('vod_content', 'watch_time')) {
                $table->integer('watch_time')->default(0)->after('views');
            }
            if (!Schema::hasColumn('vod_content', 'like_count')) {
                $table->integer('like_count')->default(0)->after('watch_time');
            }
            if (!Schema::hasColumn('vod_content', 'rating_count')) {
                $table->integer('rating_count')->default(0)->after('like_count');
            }
            if (!Schema::hasColumn('vod_content', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vod_content', function (Blueprint $table) {
            $table->dropColumn([
                'original_title', 'country', 'language', 'age_rating', 'banner_url',
                'thumbnail_url', 'tmdb_data', 'quality_badge', 'quality_updated_at',
                'is_adult', 'is_available', 'featured_order', 'views', 'watch_time',
                'like_count', 'rating_count', 'released_at'
            ]);
        });
    }
};
