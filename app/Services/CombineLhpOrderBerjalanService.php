<?php

namespace App\Services;

/**
 * @deprecated Gunakan {@see CombineLHPService} langsung.
 * Stub kompatibilitas sementara — mendelegasikan ke CombineLHPService.
 */
class CombineLhpOrderBerjalanService
{
    public const LOG_PREFIX = CombineLHPService::LOG_PREFIX;

    /** @var CombineLHPService */
    private $service;

    public function __construct()
    {
        $this->service = new CombineLHPService();
    }

    public function preview(string $noOrder, ?string $periode = null): array
    {
        return $this->service->preview($noOrder, $periode);
    }

    /**
     * @param  bool  $dryRun
     */
    public function combine(string $noOrder, ?string $periode = null, string $karyawan = 'system', bool $dryRun = true): array
    {
        return $this->service->combineFromOrderBerjalan($noOrder, $periode, $karyawan, $dryRun);
    }
}
