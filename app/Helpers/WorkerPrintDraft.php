<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\PrintDraftServices;

class WorkerPrintDraft
{
    public static function run()
    {
        $now = Carbon::now();
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $dayName = $now->format('l');
        $second = (int) $now->format('s');

        $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        if (
            in_array($dayName, $allowedDays) &&
            $hour >= 8 && $hour <= 17 &&
            $minute === 00 &&
            $second === 0
        ) {
            try {
                printf("[WorkerPrintDraft] [%s] Running...\n", $now->format('Y-m-d H:i:s'));
                PrintDraftServices::run();
            } catch (\Throwable $th) {
                Log::error($th);
            }
        }
    }
}
