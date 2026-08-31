<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->text('stream_url')->nullable()->change();
            $table->text('active_stream_url')->nullable()->change();
            $table->text('backup_url_1')->nullable()->change();
            $table->text('backup_url_2')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('stream_url')->nullable()->change();
            $table->string('active_stream_url')->nullable()->change();
            $table->string('backup_url_1')->nullable()->change();
            $table->string('backup_url_2')->nullable()->change();
        });
    }
};
