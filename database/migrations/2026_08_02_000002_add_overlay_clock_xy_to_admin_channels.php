<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->decimal('overlay_clock_x', 5, 2)->default(2.00)->after('overlay_clock_position');
            $table->decimal('overlay_clock_y', 5, 2)->default(2.00)->after('overlay_clock_x');
        });
    }

    public function down(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->dropColumn(['overlay_clock_x', 'overlay_clock_y']);
        });
    }
};
