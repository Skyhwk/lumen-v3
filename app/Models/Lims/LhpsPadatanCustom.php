<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_padatan_custom";
    public $timestamps = false;

    protected $guarded = [];
}