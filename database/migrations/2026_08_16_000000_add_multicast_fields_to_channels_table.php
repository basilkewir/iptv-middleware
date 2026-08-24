<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->unsignedInteger('program_number')->nullable()->after('stream_url')
                ->comment('MPEG-TS program/PAT number for multi-channel multicast (udp://@...) sources');
            $table->string('local_address', 45)->nullable()->after('program_number')
                ->comment('Local interface IP used to join multicast groups (udp?localaddr=)');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['program_number', 'local_address']);
        });
    }
};
