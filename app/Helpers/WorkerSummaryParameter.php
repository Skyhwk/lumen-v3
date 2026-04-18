<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Services\SummaryParameter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WorkerSummaryParameter
{
    public static function run()
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $now = Carbon::now();

        // 🔥 hanya jalan sekali per hari
        if (
            !Cache::has('summary_parameter_ran_today') &&
            $now->format('H:i') === '09:07' &&
            in_array($now->format('l'), $days)
        ) {
            try {
                Cache::put('summary_parameter_ran_today', true, Carbon::now()->endOfDay());

                Log::channel('summary_parameter')->info('SummaryParameter mulai dijalankan');

                SummaryParameter::run();

            } catch (\Throwable $th) {
                Log::channel('summary_parameter')->error(
                    'SummaryParameter gagal: ' . $th->getMessage()
                );
            }
        }
    }
}