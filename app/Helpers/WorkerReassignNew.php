<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use App\Services\RandomSalesAssign;

class WorkerReassignNew
{
    public static function run()
    {
        try {
            $feeSalesMonthly = new RandomSalesAssign();
            $feeSalesMonthly->run();
        } catch (\Throwable $th) {
            Log::error('[Worker Reassign] Error: ' . $th->getMessage());
        }
    }
}
