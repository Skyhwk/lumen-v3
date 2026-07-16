<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_swab_tes_detail";
    public $timestamps = false;

    protected $guarded = [];

     protected $casts = [
        'hasil_uji' => 'string',  // Force semua hasil_uji jadi string
    ];
}