<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterSallary extends Sector{
    protected $table = 'master_sallary';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'gaji_pokok',
        'tunjangan_kerja',
        'bulan_efektif',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'previous_id',
        'is_active',
    ];

    public $timestamps = false;

}