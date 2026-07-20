<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_iklim_detail";
    public $timestamps = false;

    protected $guarded = [];
}