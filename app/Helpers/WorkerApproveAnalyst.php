<?php

namespace App\Helpers;

use App\Services\AnalystApprove;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WorkerApproveAnalyst
{
    public static function run()
    {
        $now = Carbon::now();
        $year = (int) $now->format('Y');

        $timeStart = ['08:00:00','08:30:00','09:00:00','09:30:00','10:00:00','10:30:00','11:00:00','11:30:00','12:00:00','12:30:00','13:00:00','13:30:00','14:00:00','14:30:00','15:00:00','15:30:00','16:00:00','16:30:00','17:00:00'];


        $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];


        if (in_array($now->format('H:i:s'), $timeStart) && in_array($now->format('l'), $allowedDays)) {
            try {
                printf("[WorkerApproveAnalyst] [%s] Running untuk tahun %d\n", $now->format('Y-m-d H:i:s'), $year);
                $services = new AnalystApprove();
                $services->year($year)->run();
            } catch (\Throwable $th) {
                Log::error('[WorkerApproveAnalyst] Error: ' . $th->getMessage());
                Log::error('[WorkerApproveAnalyst] File: ' . $th->getFile() . ' Line: ' . $th->getLine());
                Log::error('[WorkerApproveAnalyst] Trace: ' . $th->getTraceAsString());
            }
        }
    }
}
