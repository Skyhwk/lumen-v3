<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class BonusKaryawan extends Sector{
    protected $table = 'bonus_karyawan';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'id_karyawan',
        'nominal',
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

    public function masterKaryawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}
