<?php

namespace App\Models;

use App\Models\Sector;
use App\Models\SarOnthespotDetail;

class SarOnthespotHeader extends Sector
{
    protected $table = "sar_onthespot_header";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->hasMany(SarOnthespotDetail::class, 'id_header', 'id');
    }

    public function hasilUji(){
        return $this->hasMany(SarDatalapangan::class, 'id_header', 'id')->with('acuan:id_parameter,nilai_rujukan');
    }
}