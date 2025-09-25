<?php

namespace App\Jobs;

use App\Services\NonaktifKaryawanService;

class NonaktifKaryawanJob extends Job
{
    protected $karyawan;
    protected $updatedBy;

    public function __construct($karyawan, $updatedBy)
    {
        $this->karyawan = $karyawan;
        $this->updatedBy = $updatedBy;
    }

    public function handle()
    {
        $service = new NonaktifKaryawanService();
        $service->nonaktifKaryawan($this->karyawan, $this->updatedBy);
    }
}
