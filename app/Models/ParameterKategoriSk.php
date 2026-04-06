<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ParameterFdl extends Sector
{
    protected $table = "parameter_kategori_sk";
    public $timestamps = false;

    protected $guarded = [];
}