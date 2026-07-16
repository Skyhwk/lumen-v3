<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_kebisingan_header_history";
    public $timestamps = false;

    protected $guarded = [];

 
}