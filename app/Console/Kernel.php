<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check for expired/expiring subscriptions daily at 9 AM
        $schedule->command('subscriptions:notify-expired')
                ->dailyAt('09:00')
                ->withoutOverlapping();

        // Send weekly encouragement messages to students every Sunday at 10 AM
        $schedule->command('subscriptions:notify-expired --weekly-encouragement')
                ->weeklyOn(0, '10:00') // 0 = Sunday
                ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}