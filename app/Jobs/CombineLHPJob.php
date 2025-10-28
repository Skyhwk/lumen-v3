<?php

namespace App\Jobs;

use App\Services\CombineLHPService;

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

    public function handle()
    {
        $service = new CombineLHPService();
        $service->combine($this->noLhp, $this->fileLhp, $this->noOrder, $this->karyawan, $this->periode);
    }
}
