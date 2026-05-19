<?php

namespace App\Models;

use App\Models\Sector;

class ParameterSar extends Sector
{
    protected $table = 'parameter_sar';
    public $timestamps = false;
    protected $guarded = [];
}