<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The overlay colour columns were sized for a single hex value
     * (`#000000cc` = 9 characters), which silently truncated the new
     * "transparent" setting to "transpare" — so the renderer never saw the
     * sentinel and kept drawing a background bar.
     */
    public function up(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->string('ticker_color', 32)->change();
            $table->string('ticker_background', 32)->change();
            $table->string('overlay_clock_color', 32)->change();
            $table->string('overlay_clock_background', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->string('ticker_color', 7)->change();
            $table->string('ticker_background', 9)->change();
            $table->string('overlay_clock_color', 7)->change();
            $table->string('overlay_clock_background', 9)->change();
        });
    }
};
