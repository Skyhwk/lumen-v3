<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganPersonalDetailHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_kebisingan_personal_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}