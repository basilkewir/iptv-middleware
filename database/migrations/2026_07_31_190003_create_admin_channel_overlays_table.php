<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channel_overlays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_channel_id');
            $table->string('overlay_name', 255);
            $table->enum('overlay_type', ['logo', 'clock', 'ticker', 'watermark', 'custom'])->default('logo');
            $table->string('overlay_url', 500)->nullable();
            $table->text('overlay_text')->nullable();
            $table->string('position', 20)->default('top-left');
            $table->integer('size')->default(100);
            $table->decimal('opacity', 3, 2)->default(1.00);
            $table->string('color', 7)->nullable();
            $table->string('background_color', 7)->nullable();
            $table->integer('z_index')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->json('animation')->nullable();
            $table->timestamps();

            $table->foreign('admin_channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->index(['admin_channel_id', 'overlay_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_overlays');
    }
};