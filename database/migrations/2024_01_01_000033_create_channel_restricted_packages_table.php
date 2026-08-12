<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_restricted_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->constrained('subscription_packages')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['channel_id', 'package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_restricted_packages');
    }
};
