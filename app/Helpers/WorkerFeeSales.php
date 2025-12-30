<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use App\Services\FeeSalesMonthly;

class WorkerFeeSales
{
    public static function run()
    {
        // if (
        //     Carbon::today()->isLastOfMonth()
        //     && Carbon::now()->hour == 22
        //     && Carbon::now()->minute == 00
        //     && Carbon::now()->second == 00
        // ) {
        //     try {
        //         $feeSalesMonthly = new FeeSalesMonthly();
        //         $feeSalesMonthly->run();
        //     } catch (\Throwable $th) {
        //         Log::error('[WorkerFeeSales] Error: ' . $th->getMessage());
        //     }
        // }
        try {
            $feeSalesMonthly = new FeeSalesMonthly();
            $feeSalesMonthly->run();
        } catch (\Throwable $th) {
            Log::error('[WorkerFeeSales] Error: ' . $th->getMessage());
        }
    }
}
