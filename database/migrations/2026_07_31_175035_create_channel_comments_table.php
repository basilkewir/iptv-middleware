<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('comment')->notNull();
            $table->boolean('is_approved')->default(false);
            $table->integer('likes')->default(0);
            $table->integer('dislikes')->default(0);
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('user_channels')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('channel_comments')->onDelete('cascade');
            $table->index('channel_id');
            $table->index('is_approved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_comments');
    }
};