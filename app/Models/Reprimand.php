<?php
namespace App\Models;

use App\Models\Sector;
use App\Models\MasterKaryawan;

class Reprimand extends Sector
{
    protected $table = 'reprimand';
    public $timestamps = false;

    // public function user() {
    //     return $this->belongsTo(User::class);
    // }

    // public function karyawan() 
    // {
    //     return $this->hasOne(MasterKaryawan::class, 'user_id', 'user_id');
    // }

    public function user()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_user', 'id');
    }

    public function reqby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'request_by', 'id');
    }

    public function approveby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'approve_by', 'id');
    }

}