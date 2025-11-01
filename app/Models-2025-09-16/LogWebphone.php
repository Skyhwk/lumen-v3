<?php
namespace App\Models;

use App\Models\Sector;

class LogWebphone extends Sector
{
    protected $table = 'log_webphone';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
