<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use App\Console\Commands\CleanOldRequestLogs;
use App\Console\Commands\ScheduleEverySecond;
use App\Console\Commands\CacheCommand;
use App\Console\Commands\ScheduleUpdateForecastSP;
use App\Console\Commands\SchaduleUpdateQsd;
use App\Console\Commands\SchaduleUpdateSummaryQsd;
use App\Console\Commands\FeeSales;
use App\Console\Commands\AssignSales;
use App\Console\Commands\BillingComand;
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
        Commands\CacheCommand::class,
        Commands\ScheduleUpdateForecastSP::class,
        Commands\SchaduleUpdateQsd::class,
        Commands\SchaduleUpdateSummaryQsd::class,
        Commands\FeeSales::class,
        Commands\AssignSales::class,
        Commands\BillingComand::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
