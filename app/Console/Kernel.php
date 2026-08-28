<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

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
        Commands\SyncQsdRevenue::class,
        Commands\SyncQsdForecast::class,
        Commands\MonitorQsdRevenue::class,
        Commands\MonitorQsdForecast::class,
        Commands\UpdateJatuhTempo::class,
        Commands\UpdateOrderDetailKonsultan::class,
        Commands\SyncLimsData::class,
        Commands\SyncSpecificLimsData::class,
        Commands\TruncateLimsTesting::class,
        Commands\SyncShioElemen::class,
        Commands\BackfillPersiapanSampel::class,
        Commands\GenerateWsFinalApproval::class,
        Commands\SyncOrderDetaolFromJadwal::class,
        Commands\SyncOrderDetail::class,
        Commands\UpdateFtcVerifierFromScanTc::class,
        Commands\TestCsTicketGeneratorCommand::class,
        Commands\SendKeptManagementDecisionReminders::class,
        Commands\CustomerServiceAutoCloseCommand::class,
        Commands\CustomerServiceAutoArchiveCommand::class,
        // Commands\LhpBackfillCommand::class,
        // Commands\LhpRefreshKpgiDetailCommand::class,
        // Commands\LhpRefreshLingHeaderCommand::class,
        // Commands\LhpRefreshIsokinetikCustomCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('recruitment:send-kept-management-reminders')
            ->dailyAt('08:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
