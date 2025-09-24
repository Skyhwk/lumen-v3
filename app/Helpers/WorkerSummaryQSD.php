<?php

namespace App\Helpers;

use App\Services\SummaryQSDServices;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WorkerSummaryQSD
{
    public static function run()
    {
        $now = Carbon::now();
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $dayName = $now->format('l');
        $second = (int) $now->format('s');
        $year = (int) $now->format('Y');

        $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        if (
            in_array($dayName, $allowedDays) &&
            $hour >= 6 && $hour <= 20 &&
            ($minute === 00 || $minute === 30) &&
            $second === 0
        ) {
            try {
                for ($i = $year; $i >= 2024; $i--) {
                    printf("[WorkerSummaryQSD] [%s] Running untuk tahun %d\n", $now->format('Y-m-d H:i:s'), $i);

                    (new SummaryQSDServices())->year($i)->run();
                }
            } catch (\Throwable $th) {
                Log::error($th);
            }
        }
    }
}
