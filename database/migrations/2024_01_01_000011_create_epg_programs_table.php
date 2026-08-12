<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epg_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epg_source_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('program_id')->nullable();
            $table->string('language')->nullable();
            $table->string('rating')->nullable();
            $table->string('category')->nullable();
            $table->string('season')->nullable();
            $table->string('episode')->nullable();
            $table->string('episode_title')->nullable();
            $table->json('subtitles')->nullable();
            $table->timestamps();
            $table->index(['channel_id', 'start_time']);
            $table->index(['epg_source_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epg_programs');
    }
};
