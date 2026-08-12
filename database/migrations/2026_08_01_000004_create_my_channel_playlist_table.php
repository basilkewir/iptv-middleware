<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_channel_playlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('content_id');

            $table->integer('order_index')->default(0);
            $table->integer('start_offset')->default(0);
            $table->integer('end_offset')->default(0);
            $table->integer('custom_duration')->default(0);

            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->json('day_of_week')->nullable();
            $table->time('time_of_day')->nullable();

            $table->string('transition_type', 20)->default('cut');
            $table->integer('transition_duration')->default(2);
            $table->string('override_quality', 20)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->foreign('content_id')->references('id')->on('my_channel_content')->onDelete('cascade');
            $table->index(['channel_id', 'order_index']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_channel_playlist');
    }
};
