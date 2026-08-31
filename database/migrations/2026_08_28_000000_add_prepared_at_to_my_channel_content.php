<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_channel_content', function (Blueprint $table) {
            $table->timestamp('prepared_at')->nullable()->after('is_transcoded');
        });
    }

    public function down(): void
    {
        Schema::table('my_channel_content', function (Blueprint $table) {
            $table->dropColumn('prepared_at');
        });
    }
};