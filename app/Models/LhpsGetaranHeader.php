<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranHeader extends Sector
{
    protected $table = "lhps_getaran_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsGetaranDetail()
    {
        return $this->hasMany(LhpsGetaranDetail::class, 'id_header', 'id');
    }

 
}