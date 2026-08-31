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

        // Ingest is now ON-DEMAND only: streams start when a client
        // requests them via ensureHlsStream(). This prevents the server
        // from being overwhelmed by 43+ simultaneous FFmpeg transcodes.
        // UDP channels use -c copy (no re-encoding) for minimal CPU usage.

        // Check every active channel's source in Flussonic and restart any
        // that are offline or frozen (bitrate = 0 / no UDP data arriving).
        // Two consecutive bad checks are required before a restart fires.
        // NOTE: streams:check-sources was removed — use channels:auto-check-health instead.

        // Source health check runs every 5 minutes instead of every minute
        // to avoid spawning ingests during overload. On-demand ingest via
        // ensureHlsStream() is the primary mechanism.
        $schedule->command('channels:auto-check-health')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Watchdog runs every minute to detect stale ingests and failover
        // to backup streams after the 10s grace period.
        $schedule->command('channels:watchdog')
            ->everyMinute()
            ->withoutOverlapping();

        // Refresh per-source statuses (primary + backups) for the admin UI on a
        // rotating subset every 3 minutes. Keeps live/offline chips real without
        // overwhelming the box with a full-catalogue ffprobe sweep.
        $schedule->command('channels:probe-sources')
            ->everyThreeMinutes()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
