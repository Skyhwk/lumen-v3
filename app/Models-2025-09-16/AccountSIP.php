<?php

namespace App\Models;

use App\Models\Sector;

class AccountSIP extends Sector
{
    // protected $table = 'account_sip';
    // // protected $connection = 'intilab_2024';

    // // public function logSIP()
    // // {
    // //     return $this->hasMany(LogSIP::class, 'from', 'username');
    // // }
    // // public function latestLogSIP()
    // // {
    // //     return $this->hasOne(LogSIP::class, 'from', 'username')->latest('created_at');
    // // }
    // public function logWebphone()
    // {
    //     return $this->hasMany(LogWebphone::class, 'karyawan_id', 'username');
    // }
    // public function latestLogWebphone()
    // {
    //     return $this->hasOne(LogWebphone::class, 'karyawan_id', 'username')->latest('created_at');
    // }

    // public function karyawan()
    // {
    //     return $this->belongsTo(MasterKaryawan::class, 'id_user', 'id');
    // }
}
