<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataPsikologi extends Sector
{
    protected $connection = 'lims';

    protected $table = "data_psikologi";
    public $timestamps = false;
    protected $guarded = [];
    
}