<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_channel_media_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('admin_channels')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('my_channel_media_folders')->onDelete('cascade');
            $table->index(['channel_id', 'parent_id']);
        });

        Schema::table('my_channel_content', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->after('channel_id');

            $table->foreign('folder_id')->references('id')->on('my_channel_media_folders')->onDelete('set null');
            $table->index('folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('my_channel_content', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });

        Schema::dropIfExists('my_channel_media_folders');
    }
};