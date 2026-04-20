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
use App\Console\Commands\AssignSales;
use App\Console\Commands\BillingComand;
use App\Console\Commands\CalculateFeeSales;
use App\Console\Commands\SummaryFeeSales;
use App\Console\Commands\KalkulasiTargetPenjadwalan;
use App\Console\Commands\ScheduleQtTransaction;
use App\Console\Commands\CheckOrderActive;
use App\Console\Commands\SummaryInvoice;
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
        Commands\ScheduleQtTransaction::class,
        Commands\AssignSales::class,
        Commands\BillingComand::class,
        Commands\CalculateFeeSales::class,
        Commands\SummaryFeeSales::class,
        Commands\KalkulasiTargetPenjadwalan::class,
        Commands\CheckOrderActive::class,
        Commands\SummaryInvoice::class,
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
