<?php
namespace App\Console\Commands;

use App\Services\SalesDailyQSD;
use App\Services\SalesKpiMonthly;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SchaduleUpdateQsd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateqsd:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Update Daily Qsd';

    public function handle()
    {
        try {
            printf("\n[SchaduleUpdateQsd] [%s] Start Running...", date('Y-m-d H:i:s'));
            SalesDailyQSD::run();
            printf("\n[SchaduleUpdateQsd] [%s] Running done, delay 3 detik sebelum hitung kpi", date('Y-m-d H:i:s'));
            sleep(3);
            printf("\n[SchaduleUpdateQsd] [%s] Start Running Kpi", date('Y-m-d H:i:s'));
            SalesKpiMonthly::run();
            printf("\n[SchaduleUpdateQsd] [%s] Running Kpi done", date('Y-m-d H:i:s'));
        } catch (\Throwable $th) {
            Log::error('[WorkerDailyQSDSales] Error: ' . $th->getMessage());
        }
    }
}