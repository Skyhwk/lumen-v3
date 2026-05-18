<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SarHeader extends Sector
{
    protected $table = "datalapangan_sar_header";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->hasMany(SarDetail::class, 'id_header', 'id')
        ->where('is_active', true);
    }
}