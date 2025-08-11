<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataSertifikatKaryawan extends Sector
{

    protected $table = 'sertifikasi_karyawan';

    protected $fillable = [
        'karyawan_id', 
        'nama_sertifikat', 
        'tipe_sertifikat', 
        'nomor_sertifikat', 
        'deskripsi_sertifikat', 
        'tgl_sertifikat', 
        'tgl_exp_sertifikat', 
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
