<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganHeader extends Sector
{
    protected $table = "lhps_kebisingan_header";
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metode_sampling' => 'array',
    ];

    public function lhpsKebisinganDetail()
    {
        return $this->hasMany(LhpsKebisinganDetail::class, 'id_header', 'id');
    }

    public function lhpsKebisinganCustom()
    {
        return $this->hasMany(LhpsKebisinganCustom::class, 'id_header', 'id');
    }
 
}