<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
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
        Commands\CalculatePoinCustomer::class,
        Commands\CalculateParameter::class,
        Commands\DeactivateExpiredBookings::class,
        // Commands\FixJadwalBookingStatus::class,
        Commands\FixJadwalSystemDeactivated::class,
        Commands\ScheduleLogTransactionQsd::class,
        Commands\GenerateWsFinalApproval::class,
        Commands\UpdateJatuhTempo::class,
        Commands\UpdateOrderDetailKonsultan::class,
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
