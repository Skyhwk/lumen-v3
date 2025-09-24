<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class AduanLapangan extends Sector
{
    protected $table = "aduan_lapangan";
    public $timestamps = false;

    protected $guarded = [];
   
}