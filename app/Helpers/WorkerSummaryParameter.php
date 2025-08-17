<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Services\SummaryParameter;
use Illuminate\Support\Facades\Log;

class WorkerSummaryParameter
{
    public static function run()
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $currentDay = Carbon::now()->format('l');
        if (Carbon::now()->format('H:i:s') == '09:07:00' && in_array($currentDay, $days)) {
            try {
                Log::channel('summary_parameter')->info('SummaryParameter mulai dijalankan');
                SummaryParameter::run();
            } catch (\Throwable $th) {
                Log::channel('summary_parameter')->error('SummaryParameter gagal dijalankan: ' . $th->getMessage());
            }
        }
    }
}
