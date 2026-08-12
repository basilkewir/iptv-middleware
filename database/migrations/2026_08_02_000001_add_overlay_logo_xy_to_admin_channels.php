<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->decimal('overlay_logo_x', 5, 2)->default(2.00)->after('overlay_logo_position');
            $table->decimal('overlay_logo_y', 5, 2)->default(2.00)->after('overlay_logo_x');
        });
    }

    public function down(): void
    {
        Schema::table('admin_channels', function (Blueprint $table) {
            $table->dropColumn(['overlay_logo_x', 'overlay_logo_y']);
        });
    }
};
