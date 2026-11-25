<?php

namespace App\Jobs;

use App\Services\CombineLHPService;
use Illuminate\Support\Facades\Log;

/**
 * Legacy job signature — sekarang mendelegasikan ke CombineLHPService
 * yang membaca order_berjalan (tanpa wajib link_lhp).
 */
class CombineLHPJob extends Job
{
    protected $noLhp;
    protected $fileLhp;
    protected $noOrder;
    protected $periode;
    protected $karyawan;

    public function __construct($noLhp, $fileLhp, $noOrder, $karyawan, $periode = null)
    {
        $this->noLhp = $noLhp;
        $this->fileLhp = $fileLhp;
        $this->noOrder = $noOrder;
        $this->periode = $periode;
        $this->karyawan = $karyawan;
    }

    /**
     * Dispatch setelah commit — kompatibel signature lama.
     */
    public static function dispatchFor($noLhp, $fileLhp, $noOrder, $karyawan, $periode = null): void
    {
        $noOrder = trim((string) $noOrder);
        if ($noOrder === '') {
            return;
        }

        $job = (new static($noLhp, $fileLhp, $noOrder, $karyawan, $periode))->afterCommit();
        dispatch($job);
    }

    public function handle()
    {
        $service = new CombineLHPService();
        $result = $service->combine(
            $this->noLhp,
            $this->fileLhp,
            $this->noOrder,
            $this->karyawan,
            $this->periode
        );

        if (is_array($result) && empty($result['ok'])) {
            Log::warning('CombineLHPJob failed', [
                'no_order' => $this->noOrder,
                'periode' => $this->periode,
                'no_lhp' => $this->noLhp,
                'spot' => $result['spot'] ?? null,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
