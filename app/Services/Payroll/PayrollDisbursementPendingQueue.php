<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Antrian payroll_header_id yang menunggu worker Python → slice callback (body kosong).
 */
class PayrollDisbursementPendingQueue
{
    private const CACHE_KEY = 'payroll_disbursement:pending_header_ids';

    public static function enqueue(int $payrollHeaderId): void
    {
        if ($payrollHeaderId <= 0) {
            return;
        }

        $ids = self::allIds();
        if (!in_array($payrollHeaderId, $ids, true)) {
            $ids[] = $payrollHeaderId;
        }

        Cache::put(self::CACHE_KEY, $ids, Carbon::now()->addDays(7));
    }

    public static function dequeueNext(): ?int
    {
        $ids = self::allIds();
        if ($ids === []) {
            return null;
        }

        $next = (int) array_shift($ids);
        Cache::put(self::CACHE_KEY, $ids, Carbon::now()->addDays(7));

        return $next > 0 ? $next : null;
    }

    public static function reenqueue(int $payrollHeaderId): void
    {
        self::enqueue($payrollHeaderId);
    }

    /**
     * @return int[]
     */
    private static function allIds(): array
    {
        $ids = Cache::get(self::CACHE_KEY, []);

        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
