<?php

namespace App\Jobs;

use App\Services\WsFinalApprovalService;

class ApproveWsParameterJob extends Job
{
    protected $data;
    protected $karyawan;

    /**
     * Create a new job instance.
     *
     * @param  mixed  $data
     * @param  string|null  $karyawan
     * @return void
     */
    public function __construct($data, ?string $karyawan = null)
    {
        $this->data = $data;
        $this->karyawan = $karyawan;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        WsFinalApprovalService::approve($this->data, $this->karyawan);
    }
}
