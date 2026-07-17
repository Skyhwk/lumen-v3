<?php
namespace App\Models\Lims;

use App\Models\Sector;

class LogWebphone extends Sector
{
    protected $connection = 'lims';

    protected $table = 'log_webphone';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
