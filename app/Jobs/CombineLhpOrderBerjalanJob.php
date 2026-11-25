<?php

namespace App\Jobs;

use App\Models\OrderBerjalan;
use App\Services\CombineLhpOrderBerjalanService;
use Illuminate\Support\Facades\Log;

/**
 * Async wrapper untuk CombineLhpOrderBerjalanService (sumber: order_berjalan).
 */
class CombineLhpOrderBerjalanJob extends Job
{
    protected $noOrder;
    protected $periode;
    protected $karyawan;

    public function __construct(string $noOrder, ?string $periode, string $karyawan)
    {
        $this->noOrder = $noOrder;
        $this->periode = $periode;
        $this->karyawan = $karyawan;
    }

    /**
     * Dispatch setelah DB commit (jika ada transaksi aktif).
     * Skip diam-diam jika snapshot order_berjalan belum ada.
     */
    public static function dispatchFor(string $noOrder, ?string $periode, string $karyawan): void
    {
        $noOrder = trim($noOrder);
        if ($noOrder === '') {
            return;
        }

        if (!OrderBerjalan::where('no_order', $noOrder)->exists()) {
            Log::warning(CombineLhpOrderBerjalanService::LOG_PREFIX . ' dispatch skipped: snapshot missing', [
                'no_order' => $noOrder,
                'periode' => $periode,
            ]);
            return;
        }

        $job = (new static($noOrder, $periode, $karyawan))->afterCommit();
        dispatch($job);
    }

    public function handle()
    {
        $service = new CombineLhpOrderBerjalanService();
        $result = $service->combine($this->noOrder, $this->periode, $this->karyawan, false);

        if (empty($result['ok'])) {
            Log::warning(CombineLhpOrderBerjalanService::LOG_PREFIX . ' job failed', [
                'no_order' => $this->noOrder,
                'periode' => $this->periode,
                'spot' => $result['spot'] ?? null,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
