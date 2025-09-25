<?php

namespace App\Models;

use App\Models\Sector;

class LogWebphoneBackup extends Sector
{
    protected $table = 'log_webphone_backup';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
