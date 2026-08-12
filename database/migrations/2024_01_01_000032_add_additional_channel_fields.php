<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('genre')->nullable()->after('description');
            $table->boolean('is_available_to_all')->default(true)->after('is_adult');
            $table->string('ip_restriction')->nullable()->after('is_available_to_all');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn([
                'genre',
                'is_available_to_all',
                'ip_restriction',
            ]);
        });
    }
};
