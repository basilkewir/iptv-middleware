<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 35)->unique()->index();
            $table->string('hotel_id', 100)->nullable()->index();
            $table->string('hotel_name');
            $table->string('device_id')->nullable();
            $table->enum('device_type', ['android_tv', 'smart_tv', 'management_backend', 'admin_panel']);
            $table->string('device_fingerprint')->nullable();
            $table->integer('max_devices')->default(1);
            $table->integer('current_devices')->default(0);
            $table->enum('license_type', ['trial', 'basic', 'premium', 'enterprise', 'perpetual'])->default('trial');
            $table->enum('status', ['active', 'expired', 'suspended', 'revoked'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->integer('validation_count')->default(0);
            $table->json('features')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'expires_at']);
            $table->index(['hotel_id', 'device_type']);
            $table->index(['license_type', 'status']);
            $table->index('last_validated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
