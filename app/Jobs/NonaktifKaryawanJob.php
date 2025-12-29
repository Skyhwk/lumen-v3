<?php

namespace App\Jobs;

use App\Services\NonaktifKaryawanService;

class NonaktifKaryawanJob extends Job
{
    protected $karyawan;

    public function __construct($karyawan)
    {
        $this->karyawan = $karyawan;
    }

    public function handle()
    {
        $service = new NonaktifKaryawanService($this->karyawan);
        $service->nonaktifKaryawan();
    }
}
