<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_categories', function (Blueprint $table) {
            $table->string('banner_image')->nullable()->after('icon');
            $table->string('color')->nullable()->after('banner_image');
            $table->enum('category_type', ['live', 'vod', 'series'])->default('live')->after('color');
            $table->boolean('auto_assign_channels')->default(false)->after('category_type');
            $table->boolean('auto_assign_vod')->default(false)->after('auto_assign_channels');
            $table->boolean('include_in_m3u')->default(true)->after('auto_assign_vod');
            $table->boolean('include_in_xmltv')->default(true)->after('include_in_m3u');
        });
    }

    public function down(): void
    {
        Schema::table('content_categories', function (Blueprint $table) {
            $table->dropColumn([
                'banner_image',
                'color',
                'category_type',
                'auto_assign_channels',
                'auto_assign_vod',
                'include_in_m3u',
                'include_in_xmltv',
            ]);
        });
    }
};
