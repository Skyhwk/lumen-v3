<?php

namespace App\Models;

use App\Models\Sector;

class ForecastSP extends Sector
{
    protected $table = 'forecast_sp';

    public $timestamps = false;
    protected $guarded = ['id'];
}
