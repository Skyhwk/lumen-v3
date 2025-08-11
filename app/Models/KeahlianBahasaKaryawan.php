<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KeahlianBahasaKaryawan extends Sector
{

    protected $table = 'skill_bahasa_karyawan';

    protected $fillable = [
        'karyawan_id',
        'bahasa',
        'baca',
        'tulis',
        'dengar',
        'bicara',
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
