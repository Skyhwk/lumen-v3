<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiIsokinetikHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisi_isokinetik_header_history";
    public $timestamps = false;

    protected $guarded = [];
}