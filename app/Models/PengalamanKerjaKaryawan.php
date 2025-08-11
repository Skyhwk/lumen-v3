<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PengalamanKerjaKaryawan extends Sector
{

    protected $table = 'pengalaman_kerja_karyawan';

    protected $fillable = [
        'karyawan_id', 
        'nama_perusahaan', 
        'lokasi_perusahaan', 
        'posisi_kerja', 
        'tgl_mulai_kerja', 
        'tgl_berakhir_kerja', 
        'alasan_keluar', 
        'created_by', 
        'created_at', 
        'updated_by', 
        'updated_at', 
        'deleted_by', 
        'deleted_at', 
        'is_active'
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
