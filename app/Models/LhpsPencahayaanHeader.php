<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanHeader extends Sector
{
    protected $table = "lhps_pencahayaan_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsPencahayaanDetail()
    {
        return $this->hasMany(LhpsPencahayaanDetail::class, 'id_header', 'id');
    }

 
}