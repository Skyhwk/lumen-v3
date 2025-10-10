<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterKendaraan extends Sector
{
    protected $table = "master_kendaraan";
    public $timestamps = false;

    protected $guarded = [];

    public function qr()
    {
        return $this->hasMany(MasterQr::class, 'id_kendaraan', 'id')->where('is_active', 1);
    }
}
