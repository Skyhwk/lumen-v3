<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterQr extends Sector
{
    protected $table = "master_qr";
    public $timestamps = false;

    protected $guarded = [];

    public function kendaraan()
    {
        return $this->belongsTo(MasterKendaraan::class, 'id', 'id_kendaraan')->where('is_active', 1);
    }
}
