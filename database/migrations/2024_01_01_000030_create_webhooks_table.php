<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('event'); // user.created, user.updated, stream.started, payment.successful, etc.
            $table->boolean('is_active')->default(true);
            $table->string('secret')->nullable(); // for webhook signing
            $table->integer('retry_count')->default(3);
            $table->integer('timeout_seconds')->default(30);
            $table->json('headers')->nullable();
            $table->json('payload_template')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $id = $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload')->nullable();
            $table->json('response_headers')->nullable();
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->integer('attempt')->default(1);
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
