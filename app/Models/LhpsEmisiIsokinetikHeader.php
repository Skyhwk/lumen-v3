<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiIsokinetikHeader extends Sector
{
    protected $table = "lhps_emisi_isokinetik_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsEmisiIsokinetikDetail()
    {
        return $this->hasMany(LhpsEmisiIsokinetikDetail::class, 'id_header', 'id');
    }

    public function lhpsEmisiIsokinetikCustom()
    {
        return $this->hasMany(LhpsEmisiIsokinetikCustom::class, 'id_header', 'id');
    }

 
}