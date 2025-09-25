<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Services\RandomSalesAssigner;
use Illuminate\Support\Facades\Log;

class WorkerReassign
{
    public static function run()
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $currentDay = Carbon::now()->format('l');
        if (Carbon::now()->format('H:i:s') == '04:00:00' && in_array($currentDay, $days)) {
            try {
                // RandomSalesAssigner::run();
            } catch (\Throwable $th) {
                Log::error($th);
            }
        }
    }
}
