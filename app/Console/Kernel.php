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
        Commands\SyncQsdRevenue::class,
        Commands\SyncQsdForecast::class,
        Commands\MonitorQsdRevenue::class,
        Commands\MonitorQsdForecast::class,
        Commands\UpdateJatuhTempo::class,
        Commands\UpdateOrderDetailKonsultan::class,
        Commands\SyncLimsData::class,
        Commands\TruncateLimsTesting::class,
        Commands\SyncShioElemen::class,
        Commands\BackfillPersiapanSampel::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // =========================================================================
        // 1. SKEMA INISIALISASI (Mencicil Data Awal Tahun, 1 Bulan per Malam)
        // =========================================================================
        
        // Senin Malam (20:00) -> Generate data Januari 2026
        $schedule->command('wsfinal:generate --month="2026-01" --chunk=100')
            ->weeklyOn(1, '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));

        // Selasa Malam (20:00) -> Generate data Februari 2026
        $schedule->command('wsfinal:generate --month="2026-02" --chunk=100')
            ->weeklyOn(2, '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));

        // Rabu Malam (20:00) -> Generate data Maret 2026
        $schedule->command('wsfinal:generate --month="2026-03" --chunk=100')
            ->weeklyOn(3, '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));

        // Kamis Malam (20:00) -> Generate data April 2026
        $schedule->command('wsfinal:generate --month="2026-04" --chunk=100')
            ->weeklyOn(4, '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));

        // Jumat Malam (20:00) -> Generate data Mei 2026
        $schedule->command('wsfinal:generate --month="2026-05" --chunk=100')
            ->weeklyOn(5, '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));

        // Sabtu Malam (20:00) -> Generate data Juni 2026
        $schedule->command('wsfinal:generate --month="2026-06" --chunk=100')
            ->weeklyOn(6, '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));

        // Minggu Malam (20:00) -> Generate data Juli 2026
        $schedule->command('wsfinal:generate --month="2026-07" --chunk=100')
            ->weeklyOn(7, '20:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));

        // =========================================================================
        // 2. SKEMA RUTIN HARIAN (Sinkronisasi LHP Baru Tiap Jam)
        // =========================================================================
        $schedule->command('wsfinal:generate --from="-3 days" --to="today" --chunk=50')
            ->hourly()
            ->between('20:00', '05:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wsfinal_generate.log'));
        // Sementara dimatikan untuk debugging — uncomment jika sudah fix
        // $schedule->command('qsd:monitor-revenue')->everyFiveMinutes();
        // $schedule->command('qsd:monitor-forecast')->everyFiveMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
