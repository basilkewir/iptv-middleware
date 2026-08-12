<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streaming_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('streaming_server_id')->nullable()->constrained()->onDelete('set null');
            $table->string('stream_url');
            $table->enum('stream_type', ['channel', 'vod']);
            $table->string('action')->default('started');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('status')->default('started');
            $table->integer('duration')->default(0);
            $table->bigInteger('bytes_sent')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['channel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaming_logs');
    }
};
