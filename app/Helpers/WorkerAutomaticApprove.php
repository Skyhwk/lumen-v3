<?php
namespace App\Helpers;

use App\Services\AutomaticApproveService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WorkerAutomaticApprove
{
    private const MIN_INTERVAL_SECONDS = 30;

    public static function run()
    {
        $lockKey = 'automatic_approve_running';

        if (Cache::has($lockKey)) {
            Log::channel('analyst_approve')->info('[WorkerAutomaticApprove] Proses sebelumnya masih berjalan, skip.');
            return;
        }

        $throttleKey = 'automatic_approve_throttle';
        if (Cache::has($throttleKey)) {
            return;
        }

        Cache::put($lockKey, true, Carbon::now()->addMinutes(5));

        try {
            (new AutomaticApproveService())->run();
        } catch (\Throwable $th) {
            Log::error('[WorkerAutomaticApprove] Error: ' . $th->getMessage());
        } finally {
            Cache::forget($lockKey);
            Cache::put($throttleKey, true, Carbon::now()->addSeconds(self::MIN_INTERVAL_SECONDS));
        }
    }
}