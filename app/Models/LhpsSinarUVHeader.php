<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSinarUVHeader extends Sector
{
    protected $table = "lhps_sinaruv_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsSinaruvDetail()
    {
        return $this->hasMany(LhpsSinarUVDetail::class, 'id_header', 'id');
    }
    public function lhpsSinaruvCustom()
    {
        return $this->hasMany(LhpsSinarUVCustom::class, 'id_header', 'id');
    }

 
}