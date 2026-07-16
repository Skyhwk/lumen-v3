<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_padatan_detail";
    public $timestamps = false;

    protected $guarded = [];
}
