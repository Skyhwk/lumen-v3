<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use App\Services\FeeSalesMonthly;

class WorkerFeeSales
{
    public static function run()
    {
        try {
            $feeSalesMonthly = new FeeSalesMonthly();
            $feeSalesMonthly->run();
        } catch (\Throwable $th) {
            Log::error('[WorkerFeeSales] Error: ' . $th->getMessage());
        }
    }
}
