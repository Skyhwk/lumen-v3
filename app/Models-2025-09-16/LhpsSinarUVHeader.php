<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSinarUVHeader extends Sector
{
    protected $table = "lhps_sinaruv_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsSinarUVDetail()
    {
        return $this->hasMany(LhpsSinarUVDetail::class, 'id_header', 'id');
    }

 
}