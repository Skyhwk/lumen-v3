<?php
namespace App\Console\Commands;

use App\Helpers\WorkerAutomaticApprove;
use App\Helpers\WorkerDailyQSDSales;
use App\Helpers\WorkerReassign;
use App\Helpers\WorkerSummaryParameter;
use App\Helpers\WorkerSummaryQSD;
use App\Helpers\WorkerUpdateKpiSales;
use App\Helpers\WorkerFeeSales;
use App\Services\EmailBlast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleEverySecond extends Command
{
    protected $signature   = 'schedule:every-second';
    protected $description = 'Run the scheduler every second (manual loop)';

    public function handle()
    {
        while (true) {
            try {
                EmailBlast::sendEmailBlast();

                // WorkerReassign::run();

                // WorkerSummaryQSD::run();

                // WorkerDailyQSDSales::run();

                // WorkerApproveAnalyst::run();

                WorkerSummaryParameter::run();
                
                // INI AUTO APPROVE
                WorkerAutomaticApprove::run();

                // WorkerUpdateKpiSales::run();

                // WorkerFeeSales::run();

                // Log::info('[ScheduleEverySecond] Loop berjalan pada: ' . date('Y-m-d H:i:s'));
            } catch (\Throwable $th) {
                Log::error($th);
            }
            sleep(1);
        }
    }
}