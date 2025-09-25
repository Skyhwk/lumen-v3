<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class RekeningKaryawan extends Sector{
    protected $table = 'rekening_karyawan';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'no_rekening',
        'nama_bank',
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