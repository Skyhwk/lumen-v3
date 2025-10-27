<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Kasbon extends Sector{
    protected $table = 'kasbon';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'total_kasbon',
        'tenor',
        'bulan_mulai_pemotongan',
        'nominal_potongan',
        'tanggal_permintaan',
        'tanggal_pencairan',
        'keterangan',
        'sisa_tenor',
        'sisa_kasbon',
        'status',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'kode_kasbon',
        'is_active',
    ];

    public $timestamps = false;
}