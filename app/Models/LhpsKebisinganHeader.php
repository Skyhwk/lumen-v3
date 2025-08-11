<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganHeader extends Sector
{
    protected $table = "lhps_kebisingan_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsKebisinganDetail()
    {
        return $this->hasMany(LhpsKebisinganDetail::class, 'id_header', 'id');
    }

 
}