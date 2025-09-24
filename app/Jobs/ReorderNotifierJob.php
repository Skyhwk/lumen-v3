<?php

namespace App\Jobs;

use App\Services\ReorderNotifierService;

class ReorderNotifierJob extends Job
{
    protected $orderHeader;
    protected $log;
    protected $bcc;
    protected $userid;

    public function __construct($orderHeader, $log, $bcc, $userid)
    {
        $this->orderHeader = $orderHeader;
        $this->log = $log;
        $this->bcc = $bcc;
        $this->userid = $userid;
    }

    public function handle()
    {
        $reorderNotifierService = new ReorderNotifierService();
        $reorderNotifierService->run($this->orderHeader, $this->log, $this->bcc, $this->userid);
    }
}
