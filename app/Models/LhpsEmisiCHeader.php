<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiCHeader extends Sector
{
    protected $table = "lhps_emisic_header";
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'header_table' => 'array',
    ];

    public function lhpsEmisiCDetail()
    {
        return $this->hasMany(LhpsEmisiCDetail::class, 'id_header', 'id');
    }

    public function lhpsEmisiCCustom()
    {
        return $this->hasMany(LhpsEmisiCCustom::class, 'id_header', 'id');
    }

    public function detail()
    {
        return $this->hasMany(LhpsEmisiCDetail::class, 'id_header', 'id');
    }

 
}