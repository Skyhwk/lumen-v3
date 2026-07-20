<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMicrobiologiHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_microbiologi_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsMicrobiologiDetailSampel()
    {
        return $this->hasMany(LhpsMicrobiologiDetail::class, 'id_header', 'id');
    }

    public function lhpsMicrobiologiDetailParameter()
    {
        return $this->hasMany(LhpsMicrobiologiDetailParameter::class, 'id_header', 'id');
    }

    // public function lhpsMicrobiologiCustomSampel()
    // {
    //     return $this->hasMany(LhpsMicrobiologiCustomSampel::class, 'id_header', 'id');
    // }

    // public function lhpsMicrobiologiCustomParameter()
    // {
    //     return $this->hasMany(LhpsMicrobiologiCustomParameter::class, 'id_header', 'id');
    // }

}