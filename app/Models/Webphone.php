<?php

namespace App\Models;

use App\Models\Sector;

class Webphone extends Sector
{
    protected $table = 'webphone';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
