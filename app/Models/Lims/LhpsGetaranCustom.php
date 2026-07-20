<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_getaran_custom";
    public $timestamps = false;

    protected $guarded = [];
}