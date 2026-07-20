<?php
namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class RfidCard extends Sector
{
    protected $connection = 'lims';

    protected $table = 'rfid_card';
    protected $guard = [];
    public $timestamps = false;

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'userid');
    }

}