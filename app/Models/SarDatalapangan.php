<?php

namespace App\Models;

use App\Models\Sector;
use App\Models\ParameterSar;

class SarDatalapangan extends Sector
{
    protected $table = "sar_datalapangan";
    public $timestamps = false;

    protected $guarded = [];

    public function acuan()
    {
        return $this->hasOne(ParameterSar::class, 'id_parameter', 'id_parameter');
    }
}