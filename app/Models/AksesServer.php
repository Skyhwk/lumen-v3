<?php

namespace App\Models;

use App\Models\Sector;
use Illuminate\Database\Eloquent\SoftDeletes;

class AksesServer extends Sector
{
    protected $table = 'akses_server';

    protected $guarded = [];
    public $timestamps = false;

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'karyawan_id');
    }
}
