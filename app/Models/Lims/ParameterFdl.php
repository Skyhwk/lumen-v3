<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ParameterFdl extends Sector
{
    protected $connection = 'lims';

    protected $table = "parameter_fdl";
    public $timestamps = false;

    protected $guarded = [];
}