<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DendaKaryawan extends Sector{
    protected $table = 'denda_karyawan';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'kode_denda',
        'total_denda',
        'tenor',
        'bulan_mulai_pemotongan',
        'nominal_potongan',
        'keterangan',
        'sisa_tenor',
        'sisa_denda',
        'status',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_active',
    ];

    public $timestamps = false;
}