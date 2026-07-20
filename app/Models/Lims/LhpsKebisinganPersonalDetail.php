<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganPersonalDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_kebisingan_personal_detail";
    public $timestamps = false;

    protected $guarded = [];
}