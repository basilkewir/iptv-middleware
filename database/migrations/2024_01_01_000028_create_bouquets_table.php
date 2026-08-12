<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bouquets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('subscription_packages')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 10, 2)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('bouquets')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bouquet_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bouquet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bouquet_vod', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bouquet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vod_content_id')->constrained('vod_content')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bouquet_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bouquet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('bouquet_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bouquet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_package_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bouquet_package');
        Schema::dropIfExists('bouquet_user');
        Schema::dropIfExists('bouquet_vod');
        Schema::dropIfExists('bouquet_channels');
        Schema::dropIfExists('bouquets');
    }
};
