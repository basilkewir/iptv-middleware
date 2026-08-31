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
            $table->text('youtube_url_1')->nullable()->after('youtube_verified_at');
            $table->boolean('youtube_url_1_verified')->default(false)->after('youtube_url_1');
            $table->text('youtube_url_2')->nullable()->after('youtube_url_1_verified');
            $table->boolean('youtube_url_2_verified')->default(false)->after('youtube_url_2');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn([
                'youtube_url_1',
                'youtube_url_1_verified',
                'youtube_url_2',
                'youtube_url_2_verified',
            ]);
        });
    }
};
