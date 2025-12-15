<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmailBlast;
use App\Helpers\WorkerReassign;
use App\Helpers\WorkerSummaryParameter;
use App\Helpers\WorkerSummaryQSD;
use App\Helpers\WorkerApproveAnalyst;
use App\Helpers\WorkerUpdateKpiSales;
use App\Helpers\WorkerFeeSales;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleEverySecond extends Command
{
    protected $signature = 'schedule:every-second';
    protected $description = 'Run the scheduler every second (manual loop)';

    public function handle()
    {
        while (true) {
            try {
                EmailBlast::sendEmailBlast();

                WorkerReassign::run();

                WorkerSummaryQSD::run();

                // WorkerApproveAnalyst::run();

                WorkerSummaryParameter::run();

                WorkerUpdateKpiSales::run();

                WorkerFeeSales::run();

                // Log::info('[ScheduleEverySecond] Loop berjalan pada: ' . date('Y-m-d H:i:s'));
            } catch (\Throwable $th) {
                Log::error($th);
            }
            sleep(1);
        }
    }
}
