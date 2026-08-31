<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->json('source_statuses_json')->nullable()->after('source_last_error');
            $table->timestamp('sources_last_probed_at')->nullable()->after('source_statuses_json');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['source_statuses_json', 'sources_last_probed_at']);
        });
    }
};