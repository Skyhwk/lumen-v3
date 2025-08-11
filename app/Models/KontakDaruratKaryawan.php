<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KontakDaruratKaryawan extends Sector
{

    protected $table = 'kontak_darurat_karyawan';

    protected $fillable = [
        'karyawan_id', 
        'nama_kontak', 
        'hubungan', 
        'nomor_kontak', 
        'created_by', 
        'updated_by', 
        'deleted_by', 
        'is_active'
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
