<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class RfidCard extends Sector
{
    protected $table = 'rfid_card';
    protected $guard = [];
    public $timestamps = false;

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'userid');
    }

}