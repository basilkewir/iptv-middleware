<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_channel_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();

            $table->enum('broadcast_mode', ['24_7', 'scheduled', 'time_limited'])->default('24_7');
            $table->string('broadcast_timezone', 50)->default('UTC');

            $table->string('default_transition', 20)->default('cut');
            $table->integer('transition_duration')->default(2);
            $table->integer('buffer_between_items')->default(0);

            $table->boolean('fallback_enabled')->default(true);
            $table->unsignedBigInteger('fallback_playlist_id')->nullable();
            $table->boolean('fallback_after_empty')->default(true);

            $table->string('default_quality', 20)->default('hd');
            $table->boolean('auto_adjust_quality')->default(true);

            $table->boolean('notify_low_content')->default(true);
            $table->integer('low_content_threshold')->default(10);
            $table->boolean('notify_broadcast_start')->default(true);
            $table->boolean('notify_broadcast_end')->default(true);

            $table->boolean('enable_dvr')->default(false);
            $table->boolean('enable_timeshift')->default(false);
            $table->integer('timeshift_duration')->default(0);

            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_channel_settings');
    }
};
