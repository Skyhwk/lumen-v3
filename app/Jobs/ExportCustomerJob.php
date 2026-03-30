<?php

namespace App\Jobs;

use App\Services\ExportCustomerService;

class ExportCustomerJob extends Job
{
    protected $id;
    protected $type;
    protected $status;
    protected $typeQt;
    protected $category;
    protected $duration;

    public function __construct($id, $type, $status, $typeQt, $category, $duration)
    {
        $this->id = $id;
        $this->type = $type;
        $this->status = $status;
        $this->typeQt = $typeQt;
        $this->duration = $duration;
        $this->category = $category;
    }

    public function handle()
    {
        $service = new ExportCustomerService();
        $service->export($this->id, $this->type, $this->status, $this->typeQt, $this->category, $this->duration);
    }
}
