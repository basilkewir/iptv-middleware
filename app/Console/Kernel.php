<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('subscriptions:process-expired')
            ->daily()
            ->at('01:00')
            ->withoutOverlapping();

        $schedule->command('invoices:generate')
            ->daily()
            ->at('02:00')
            ->withoutOverlapping();

        $schedule->command('epg:update')
            ->everyFourHours()
            ->withoutOverlapping();

        $schedule->command('servers:monitor')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Keep every active channel playing locally (local-network HLS) and
        // respawn any ingest that has died. Idempotent: channels already
        // running are skipped.
        $schedule->command('channels:ingest-all')
            ->everyMinute()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
