<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ParameterFdl extends Sector
{
    protected $table = "parameter_fdl";
    public $timestamps = false;

    protected $guarded = [];
}