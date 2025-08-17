<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimHeader extends Sector
{
    protected $table = "lhps_iklim_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsIklimDetail()
    {
        return $this->hasMany(LhpsIklimDetail::class, 'id_header', 'id');
    }

 
}