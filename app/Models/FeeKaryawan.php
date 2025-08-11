<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class FeeKaryawan extends Sector{
    protected $table = 'fee_karyawan';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'id_master_fee',
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