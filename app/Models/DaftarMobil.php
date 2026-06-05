<?php

namespace App\Models;

use App\Models\Sector;

class DaftarMobil extends Sector
{
    protected $table = 'daftar_mobil';

    protected $fillable = [
        'plat_mobil',
        'merk_mobil',
        'tipe_mobil',
        'nomor_rangka',
        'nomor_mesin',
        'warna_mobil',
        'tahun_perakitan',
        'status_gps',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_active',
    ];

    protected $casts = [
        'status_gps' => 'boolean',
        'is_active' => 'boolean',
    ];

    public $timestamps = false;
}
