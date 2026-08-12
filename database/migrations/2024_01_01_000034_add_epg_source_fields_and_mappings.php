<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epg_sources', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->after('type');
            $table->string('timezone')->nullable()->after('language');
            $table->boolean('auto_mapping')->default(false)->after('timezone');
            $table->string('mapping_strategy')->default('name')->after('auto_mapping');
        });

        Schema::create('epg_channel_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epg_source_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('epg_channel_id');
            $table->string('epg_channel_name')->nullable();
            $table->boolean('is_auto_matched')->default(false);
            $table->timestamps();

            $table->unique(['epg_source_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epg_channel_mappings');
        Schema::table('epg_sources', function (Blueprint $table) {
            $table->dropColumn(['language', 'timezone', 'auto_mapping', 'mapping_strategy']);
        });
    }
};
