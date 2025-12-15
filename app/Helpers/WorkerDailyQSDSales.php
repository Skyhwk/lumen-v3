<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Services\SalesDailyQSD;
use Illuminate\Support\Facades\Log;

class WorkerDailyQSDSales
{
    public static function run()
    {
        $now = Carbon::now();
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $second = (int) $now->format('s');
        $year = (int) $now->format('Y');

        // Jalankan setiap 15 menit dari jam 08:00 sampai 20:00 setiap hari, pada detik ke-0
        if (
            $hour >= 8 && $hour <= 20 &&
            $minute % 15 === 0 &&
            $second === 0
        ) {
            try {
                SalesDailyQSD::run();
            } catch (\Throwable $th) {
                Log::error('[WorkerDailyQSDSales] Error: ' . $th->getMessage());
            }
        }
    }
}
