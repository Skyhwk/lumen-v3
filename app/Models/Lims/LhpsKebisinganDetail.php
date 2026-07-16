<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_kebisingan_detail";
    public $timestamps = false;

    protected $guarded = [];
}