<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMHeader extends Sector
{
    protected $table = "lhps_medanlm_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsMedanLMDetail()
    {
        return $this->hasMany(LhpsMedanLMDetail::class, 'id_header', 'id');
    }

 
}