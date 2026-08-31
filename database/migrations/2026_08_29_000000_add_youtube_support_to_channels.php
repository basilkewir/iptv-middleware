<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->enum('source_type', ['stream', 'youtube'])->default('stream')->after('stream_type');
            $table->text('source_url')->nullable()->after('active_stream_url');
            $table->text('youtube_url')->nullable()->after('source_url');
            $table->json('youtube_cookies')->nullable()->after('youtube_url');
            $table->boolean('youtube_verified')->default(false)->after('youtube_cookies');
            $table->timestamp('youtube_verified_at')->nullable()->after('youtube_verified');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn([
                'source_type',
                'source_url',
                'youtube_url',
                'youtube_cookies',
                'youtube_verified',
                'youtube_verified_at',
            ]);
        });
    }
};