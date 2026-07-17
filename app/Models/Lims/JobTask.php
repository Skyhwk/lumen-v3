<?php

namespace App\Models\Lims;

use App\Models\Sector;

class JobTask extends Sector
{
    protected $connection = 'lims';
    protected $table = "job_task";
    public $timestamps = false;
}
