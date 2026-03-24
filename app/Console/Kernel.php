<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('logs:prune-file-access')->daily();
        $schedule->command('metrics:daily')->dailyAt('00:30');
        $schedule->command('iwosan:send-appointment-reminders')->hourly();
        $schedule->command('iwosan:send-payment-reminders')->hourly();
        $schedule->command('iwosan:send-lab-result-notifications')->hourly();
        $schedule->command('iwosan:monitor-home-workflow-sla')->everyTenMinutes();
        $schedule->command('iwosan:monitor-teletest-workflow-sla')->everyTenMinutes();
        $schedule->command('iwosan:monitor-virtual-visit-workflow-sla')->everyTenMinutes();
        $schedule->command('compliance:purge-retention')->dailyAt('02:15');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
