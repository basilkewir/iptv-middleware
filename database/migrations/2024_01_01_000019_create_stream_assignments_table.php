<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->foreignId('streaming_server_id')->constrained()->onDelete('cascade');
            $table->string('stream_url');
            $table->string('backup_stream_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->integer('load_balance_weight')->default(1);
            $table->timestamps();
            $table->unique(['channel_id', 'streaming_server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_assignments');
    }
};
