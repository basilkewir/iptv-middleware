<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epg_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->enum('type', ['xmltv', 'json', 'custom'])->default('xmltv');
            $table->integer('update_interval')->default(3600);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('next_fetch_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epg_sources');
    }
};
