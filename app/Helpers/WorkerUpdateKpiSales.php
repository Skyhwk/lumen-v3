<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Services\SalesKpiMonthly;
use Illuminate\Support\Facades\Log;

class WorkerUpdateKpiSales
{
    public static function run()
    {
        $now = Carbon::now();
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $second = (int) $now->format('s');
        $year = (int) $now->format('Y');

        // Jalankan setiap 10 menit dari jam 06:00 sampai 21:00 setiap hari, pada detik ke-0
        if (
            $hour >= 6 && $hour <= 21 &&
            $minute % 5 === 0 &&
            $second === 0
        ) {
            try {
                SalesKpiMonthly::run();
            } catch (\Throwable $th) {
                Log::error('[WorkerUpdateKpiSales] Error: ' . $th->getMessage());
            }
        }
    }
}
