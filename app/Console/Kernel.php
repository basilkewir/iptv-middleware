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

        $schedule->command('channels:auto-check-health')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('channels:watchdog')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('channels:probe-sources')
            ->everyThreeMinutes()
            ->withoutOverlapping();

        $schedule->command('channels:purge-ffmpeg')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('push:watch')
            ->everyMinute()
            ->withoutOverlapping();

        // Ensure all active channel ingests are running (persistent background
        // ingestion — the core of the standalone XC-VM-style architecture).
        $schedule->command('ingest:ensure-all')
            ->everyMinute()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
