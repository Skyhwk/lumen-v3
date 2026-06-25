<?php

namespace App\Console\Commands;

use App\Helpers\WorkerAutomaticApprove;
use App\Helpers\WorkerSummaryParameter;
use App\Services\EmailBlast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleEverySecond extends Command
{
    protected $signature   = 'schedule:every-second';
    protected $description = 'Run optimized scheduler every second';

    public function handle()
    {
        while (true) {
            try {
                WorkerAutomaticApprove::run();

                if (Carbon::now()->second % 5 === 0) {
                    EmailBlast::sendEmailBlast();
                }

                if (Carbon::now()->second === 0) {
                    WorkerSummaryParameter::run();
                }

            } catch (\Throwable $th) {
                Log::error($th);
            }

            sleep(1);
        }
    }
}