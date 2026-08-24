<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('active_stream_url')->nullable()->after('stream_url');
            $table->unsignedTinyInteger('active_source_index')->default(0)->after('active_stream_url');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['active_stream_url', 'active_source_index']);
        });
    }
};
