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
        Schema::create('license_validation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id')->nullable();
            $table->string('device_id')->nullable();
            $table->string('validation_type')->default('initial');
            $table->enum('status', ['success', 'invalid', 'expired', 'blocked', 'failed'])->default('invalid');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->float('processing_time')->nullable();
            $table->timestamp('validated_at')->nullable();

            $table->foreign('license_id')->references('id')->on('licenses')->onDelete('cascade');
            $table->index(['license_id', 'status']);
            $table->index('validated_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_validation_logs');
    }
};