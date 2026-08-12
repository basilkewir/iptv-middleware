<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channel_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_channel_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_package_id')->nullable();
            $table->enum('status', ['active', 'inactive', 'expired', 'cancelled'])->default('active');
            $table->timestamp('subscribed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('admin_channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subscription_package_id')->references('id')->on('subscription_packages')->onDelete('set null');
            $table->unique(['admin_channel_id', 'user_id']);
            $table->index(['admin_channel_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_subscriptions');
    }
};