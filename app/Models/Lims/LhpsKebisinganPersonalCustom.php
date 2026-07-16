<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganPersonalCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_kebisingan_personal_custom";
    public $timestamps = false;

    protected $guarded = [];
}