<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('transcoding_device', 10)->nullable()->after('transcoding_enabled')->comment('cpu or gpu');
        });

        Schema::table('admin_channels', function (Blueprint $table) {
            $table->string('transcoding_device', 10)->nullable()->after('audio_codec')->comment('cpu or gpu');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('transcoding_device');
        });

        Schema::table('admin_channels', function (Blueprint $table) {
            $table->dropColumn('transcoding_device');
        });
    }
};
