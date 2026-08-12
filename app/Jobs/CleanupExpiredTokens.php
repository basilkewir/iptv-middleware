<?php

namespace App\Jobs;

use App\Models\PasswordResetToken;
use App\Models\RememberToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct() {}

    public function handle(): void
    {
        $deletedCount = 0;

        $deletedCount += PasswordResetToken::where('created_at', '<', now()->subHours(24))->delete();

        $deletedCount += DB::table('personal_access_tokens')
            ->where('expires_at', '<', now())
            ->delete();

        $deletedCount += DB::table('sessions')
            ->where('last_activity', '<', now()->subDays(7)->timestamp)
            ->delete();

        Log::info('Expired tokens cleaned up', ['deleted_count' => $deletedCount]);
    }
}
