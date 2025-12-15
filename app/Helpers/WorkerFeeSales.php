<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use App\Services\FeeSalesMonthly;

class WorkerFeeSales
{
    public static function run()
    {
        if (
            Carbon::today()->isLastOfMonth()
            && Carbon::now()->hour == 23
            && Carbon::now()->minute == 59
            && Carbon::now()->second == 59
        ) {
            try {
                $feeSalesMonthly = new FeeSalesMonthly();
                $feeSalesMonthly->run();
            } catch (\Throwable $th) {
                Log::error('[WorkerFeeSales] Error: ' . $th->getMessage());
            }
        }
    }
}
