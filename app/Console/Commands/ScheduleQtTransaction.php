<?php
namespace App\Console\Commands;

use App\Helpers\WorkerAutomaticApprove;
use App\Helpers\WorkerDailyQSDSales;
use App\Helpers\WorkerReassign;
use App\Helpers\WorkerSummaryParameter;
use App\Helpers\WorkerSummaryQSD;
use App\Helpers\WorkerUpdateKpiSales;
use App\Helpers\WorkerFeeSales;
use App\Helpers\WorkerQtTransactionNonKontrak;
use App\Services\EmailBlast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleQtTransaction extends Command
{
    protected $signature   = 'schedule:qt-transaction';
    protected $description = 'Run the scheduler for qt transactions';

    public function handle()
    {
        while (true) {
            try {
                printf("\n[ScheduleQtTransactions] [%s] Start Running...", date('Y-m-d H:i:s'));

                WorkerQtTransactionNonKontrak::run();

                printf("\n[ScheduleQtTransactions] [%s] Running done", date('Y-m-d H:i:s'));
            } catch (\Throwable $th) {
                Log::error($th);
            }
            sleep(1);
        }
    }
}
