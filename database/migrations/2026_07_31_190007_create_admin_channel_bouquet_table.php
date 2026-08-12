<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_channel_bouquet', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_channel_id');
            $table->unsignedBigInteger('bouquet_id');
            $table->timestamps();

            $table->foreign('admin_channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->foreign('bouquet_id')->references('id')->on('bouquets')->onDelete('cascade');
            $table->unique(['admin_channel_id', 'bouquet_id'], 'acb_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_channel_bouquet');
    }
};