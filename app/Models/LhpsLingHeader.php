<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingHeader extends Sector
{
    protected $table = "lhps_ling_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsLingDetail()
    {
        return $this->hasMany(LhpsLingDetail::class, 'id_header', 'id');
    }

 
}