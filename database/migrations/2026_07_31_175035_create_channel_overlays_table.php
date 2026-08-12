<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_overlays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->enum('overlay_type', ['ticker', 'logo', 'watermark', 'clock', 'timer', 'custom'])->notNull();
            $table->string('overlay_name', 100)->nullable();
            $table->text('ticker_text')->nullable();
            $table->integer('ticker_speed')->default(30);
            $table->enum('ticker_direction', ['left', 'right', 'up', 'down'])->default('left');
            $table->integer('ticker_font_size')->default(24);
            $table->string('ticker_font_color', 7)->nullable();
            $table->string('ticker_background_color', 7)->nullable();
            $table->decimal('ticker_opacity', 3, 2)->default(1.00);
            $table->string('logo_url', 500)->nullable();
            $table->enum('logo_position', ['top-left', 'top-center', 'top-right', 'middle-left', 'middle-center', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right'])->default('top-left');
            $table->integer('logo_size')->default(100);
            $table->decimal('logo_opacity', 3, 2)->default(1.00);
            $table->integer('logo_margin_x')->default(10);
            $table->integer('logo_margin_y')->default(10);
            $table->string('clock_format', 20)->default('HH:MM:SS');
            $table->string('clock_timezone', 50)->nullable();
            $table->integer('clock_font_size')->default(24);
            $table->string('clock_font_color', 7)->nullable();
            $table->string('clock_background_color', 7)->nullable();
            $table->string('clock_position', 20)->default('top-right');
            $table->integer('display_duration')->default(0);
            $table->integer('start_delay')->default(0);
            $table->integer('end_advance')->default(0);
            $table->integer('z_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('user_channels')->onDelete('cascade');
            $table->index(['channel_id', 'overlay_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_overlays');
    }
};