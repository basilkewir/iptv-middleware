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
        Schema::create('license_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id');
            $table->string('device_id')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable();
            $table->string('device_model')->nullable();
            $table->string('device_os')->nullable();
            $table->string('device_os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->integer('activation_count')->default(1);
            $table->timestamp('first_activated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('license_id')->references('id')->on('licenses')->onDelete('cascade');
            $table->index(['license_id', 'device_fingerprint']);
            $table->index('status');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_devices');
    }
};