<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Overlay typography controls.
     *
     * The Stage 2 graph previously derived every font size from the output
     * height (3.5% for the ticker, 3% for the clock), so there was no way to
     * tune how large the on-air text is. These are percentages of the previous
     * default so 100 (or NULL) keeps the existing look exactly.
     */
    public function up(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            // 100 = the historic size; scales with the output resolution.
            $table->unsignedSmallInteger('ticker_font_size')->nullable()->after('ticker_direction');
            $table->unsignedSmallInteger('overlay_clock_font_size')->nullable()->after('overlay_clock_format');

            // Clock styling, previously hard-coded to white on 50% black.
            $table->string('overlay_clock_color', 32)->nullable()->after('overlay_clock_font_size');
            $table->string('overlay_clock_background', 64)->nullable()->after('overlay_clock_color');
        });
    }

    public function down(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->dropColumn([
                'ticker_font_size',
                'overlay_clock_font_size',
                'overlay_clock_color',
                'overlay_clock_background',
            ]);
        });
    }
};
