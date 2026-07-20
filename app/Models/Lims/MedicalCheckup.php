<?php
namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MedicalCheckup extends Sector
{
    protected $connection = 'lims';


    protected $table = 'rekam_medis_karyawan';

    protected $fillable = [
        'karyawan_id', 
        'tinggi_badan', 
        'berat_badan', 
        'keterangan_mata', 
        'rate_mata', 
        'golongan_darah', 
        'penyakit_bawaan_lahir', 
        'penyakit_kronis', 
        'riwayat_kecelakaan', 
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