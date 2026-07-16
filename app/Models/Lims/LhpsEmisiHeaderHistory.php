<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisi_header_history";
    public $timestamps = false;

    protected $guarded = [];
}