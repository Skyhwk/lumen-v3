<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DepresiasiAset extends Sector
{
    protected $table = "depresiasi_aset";

    public $timestamps = false;

    protected $guarded = [];

    public function master_aset()
    {
        return $this->belongsTo(MasterAset::class, 'id_master_aset', 'id');
    }
}



