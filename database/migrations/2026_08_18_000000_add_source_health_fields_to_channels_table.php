<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('source_status', 20)->nullable()->after('local_address')
                ->comment('Last known source health: online, offline, unknown');
            $table->timestamp('source_last_checked_at')->nullable()->after('source_status')
                ->comment('When the source was last probed');
            $table->timestamp('source_last_online_at')->nullable()->after('source_last_checked_at')
                ->comment('When the source was last seen online');
            $table->unsignedInteger('source_check_attempts')->default(0)->after('source_last_online_at')
                ->comment('Consecutive failed check attempts');
            $table->text('source_last_error')->nullable()->after('source_check_attempts')
                ->comment('Last error message from health check');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn([
                'source_status',
                'source_last_checked_at',
                'source_last_online_at',
                'source_check_attempts',
                'source_last_error',
            ]);
        });
    }
};
