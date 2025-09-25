<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PencadanganUpah extends Sector{
    protected $table = 'pencadangan_upah';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'tenor',
        'nominal',
        'tenor_berjalan',
        'bulan_efektif',
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