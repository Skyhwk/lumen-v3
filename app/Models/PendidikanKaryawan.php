<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PendidikanKaryawan extends Sector
{

    protected $table = 'pendidikan_karyawan';

    protected $fillable = [
        'karyawan_id', 
        'institusi', 
        'jenjang', 
        'jurusan', 
        'tahun_masuk', 
        'tahun_lulus', 
        'kota', 
        'created_by',
        'created_at', 
        'updated_by',
        'updated_at', 
        'deleted_by',
        'deleted_at', 
        'is_active',
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
