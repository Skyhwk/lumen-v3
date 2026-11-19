<?php

namespace App\Models;

use App\Models\Sector;

class MasterAsetKaryawan extends Sector
{
    protected $table = 'master_aset_karyawan';

    protected $fillable = [
        'nama',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $timestamps = false;

    public function asetKaryawan()
    {
        return $this->hasMany(AsetKaryawan::class, 'master_aset_karyawan_id');
    }
}
