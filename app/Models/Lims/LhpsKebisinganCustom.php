<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_kebisingan_custom";
    public $timestamps = false;

    protected $guarded = [];
}