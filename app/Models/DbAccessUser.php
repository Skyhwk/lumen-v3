<?php

namespace App\Models;

use App\Models\Sector;

class DbAccessUser extends Sector
{
    protected $table = "db_access_users";
    protected $guarded = ["id"];
    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}
