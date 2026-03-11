<?php

namespace App\Models;

use App\Models\Sector;

class SalesKpi extends Sector
{
    protected $table = 'sales_kpi_monthly';
    public $timestamps = false;
    protected $guarded = [];
}