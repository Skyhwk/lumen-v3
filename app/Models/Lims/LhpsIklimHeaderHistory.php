<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_iklim_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}