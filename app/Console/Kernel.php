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
        Commands\SyncSamplerTracking::class,
        Commands\CollectSamplerTrackingTroubles::class,
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
        Commands\CollectMonitorKeterlambatanAnalisa::class,
        Commands\TestCsTicketGeneratorCommand::class,
        Commands\SendKeptManagementDecisionReminders::class,
        Commands\SendCandidateActionReminders::class,
        Commands\SendPendingAssessmentInvitations::class,
        Commands\RejectOverdueAssessment::class,
        Commands\RollbackOverdueAssessment::class,
        Commands\ApplyScheduledEmployeeAdjustments::class,
        Commands\CustomerServiceAutoCloseCommand::class,
        Commands\CustomerServiceAutoArchiveCommand::class,
        Commands\MigrateHrRequestsFromAppsCommand::class,
        Commands\VerifyHrMigrationCommand::class,
        Commands\VerifyHrStatusParityCommand::class,
        Commands\SmokeHrWorkflowCommand::class,
        Commands\SendGreatdayTestNotificationCommand::class,
        Commands\DetectGreatdayAttendanceRemindersCommand::class,
        Commands\SendGreatdayAttendanceRemindersCommand::class,
        Commands\MigrateGdAuthFromAppsCommand::class,
        Commands\MigrateGdAppDataFromAppsCommand::class,
        Commands\VerifyGdAuthParityCommand::class,
        Commands\MigrateHrExtendedFormsFromAppsCommand::class,
        Commands\ImportHrLeaveOpeningBalanceCommand::class,
        Commands\SyncHrSpecialLeaveTypesCommand::class,
        Commands\SyncLeaveAlpaLedgerCommand::class,
        Commands\SyncGreatdayFormAttachmentFilesCommand::class,
        Commands\ImportGreatdayAssetsFromInternalCommand::class,
        Commands\QuotationDiscoverCommand::class,
        Commands\QuotationCreateCommand::class,
        Commands\QuotationDispatchCommand::class,
        Commands\QuotationGenerateCommand::class,
        Commands\GenerateMonthlyAbsensiCommand::class,
        // Commands\LhpBackfillCommand::class,
        // Commands\LhpRefreshKpgiDetailCommand::class,
        // Commands\LhpRefreshLingHeaderCommand::class,
        // Commands\LhpRefreshIsokinetikCustomCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('sampler-tracking:collect-troubles --sync-today')
        //     ->dailyAt('00:00')->timezone('Asia/Jakarta')->withoutOverlapping(60);
        // $schedule->command('recruitment:send-pending-assessment-invitations')
        //     ->everyFiveMinutes()
        //     ->timezone('Asia/Jakarta')
        //     ->withoutOverlapping();
        // Belum diaktifkan. Nyalakan hanya setelah command assessmentrejection dicek manual.
        // $schedule->command('assessmentrejection')
        //     ->dailyAt('17:00')
        //     ->timezone('Asia/Jakarta')
        //     ->withoutOverlapping();
        // Manual dulu per kategori. Nanti aktifkan jika sudah siap otomatis jam 11 malam:
        // $schedule->command('collect:monitor-keterlambatan-analisa')
        //     ->dailyAt('23:00')
        //     ->timezone('Asia/Jakarta')
        //     ->withoutOverlapping();
        $schedule->command('employee-adjustment:apply-scheduled')
            ->dailyAt('07:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(30);
        // $schedule->command('absensi:generate-monthly')
        //     ->monthlyOn(1, '10:00')
        //     ->timezone('Asia/Jakarta')
        //     ->withoutOverlapping(180);

        // Reminder absensi Greatday — aktifkan setelah ops tim infra (bukan bagian dev feature).
        // $schedule->command('greatday:attendance-reminder-send --slot=morning')
        //     ->dailyAt('09:00')
        //     ->timezone('Asia/Jakarta')
        //     ->withoutOverlapping(45);
        // $schedule->command('greatday:attendance-reminder-send --slot=evening')
        //     ->dailyAt('21:00')
        //     ->timezone('Asia/Jakarta')
        //     ->withoutOverlapping(45);
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
