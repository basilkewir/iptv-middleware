<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('subscription_type', ['free', 'premium', 'trial'])->default('free');
            $table->timestamp('start_date')->useCurrent();
            $table->timestamp('end_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->boolean('notify_new_content')->default(true);
            $table->boolean('notify_schedule')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('user_channels')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['channel_id', 'user_id']);
            $table->index(['channel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_subscriptions');
    }
};