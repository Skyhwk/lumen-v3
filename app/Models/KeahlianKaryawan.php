<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KeahlianKaryawan extends Sector
{

    protected $table = 'keahlian_karyawan';

    protected $fillable = [
        'karyawan_id',
        'keahlian',
        'rate',
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
