<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataPsikologi extends Sector
{
    protected $table = "data_psikologi";
    public $timestamps = false;
    protected $guarded = [];
    
}