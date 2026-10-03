<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_channel_playlist', function (Blueprint $table) {
            $table->string('category', 20)->default('program')->after('content_id');
        });
    }

    public function down(): void
    {
        Schema::table('my_channel_playlist', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
