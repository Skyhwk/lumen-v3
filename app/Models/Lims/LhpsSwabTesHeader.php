<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_swab_tes_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsSwabTesDetail()
    {
        return $this->hasMany(LhpsSwabTesDetail::class, 'id_header', 'id');
    }

    // public function lhpsSwabTesDetailParameter()
    // {
    //     return $this->hasMany(LhpsSwabTesDetailParameter::class, 'id_header', 'id');
    // }
    

    // public function lhpsSwabTesCustomSampel()
    // {
    //     return $this->hasMany(LhpsSwabTesCustomSampel::class, 'id_header', 'id');
    // }

    // public function lhpsSwabTesCustomParameter()
    // {
    //     return $this->hasMany(LhpsSwabTesCustomParameter::class, 'id_header', 'id');
    // }

}