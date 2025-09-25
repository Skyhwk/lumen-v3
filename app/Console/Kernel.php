<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use App\Console\Commands\CleanOldRequestLogs;
use App\Console\Commands\ScheduleEverySecond;
use App\Console\Commands\CacheCommand;
use App\Services\{EmailBlast};
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CleanOldRequestLogs::class,
        Commands\ScheduleEverySecond::class,
        Commands\CacheCommand::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->call(function () {
        //     EmailBlast::sendEmailBlast();
        // })->everySecond();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
