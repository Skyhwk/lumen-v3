<?php

namespace App\Jobs;

use App\Models\OrderBerjalan;
use App\Services\CombineLHPService;
use Illuminate\Support\Facades\Log;

/**
 * Async combine LHP dari snapshot order_berjalan.
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
     * Skip + log jika snapshot order_berjalan belum ada.
     */
    public static function dispatchFor(string $noOrder, ?string $periode, string $karyawan): void
    {
        $noOrder = trim($noOrder);
        if ($noOrder === '') {
            return;
        }

        if (!OrderBerjalan::where('no_order', $noOrder)->exists()) {
            Log::warning(CombineLHPService::LOG_PREFIX . ' dispatch skipped: snapshot missing', [
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
        $service = new CombineLHPService();
        $result = $service->combineFromOrderBerjalan(
            $this->noOrder,
            $this->periode,
            $this->karyawan,
            false
        );

        if (empty($result['ok'])) {
            Log::warning(CombineLHPService::LOG_PREFIX . ' job failed', [
                'no_order' => $this->noOrder,
                'periode' => $this->periode,
                'spot' => $result['spot'] ?? null,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
