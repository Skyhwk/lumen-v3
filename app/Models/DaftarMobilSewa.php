<?php

namespace App\Models;

use App\Models\Sector;

class DaftarMobilSewa extends Sector
{
    protected $table = 'daftar_mobil_sewa';

    protected $fillable = [
        'plat_mobil',
        'merk_mobil',
        'tipe_mobil',
        'nomor_rangka',
        'nomor_mesin',
        'warna_mobil',
        'tahun_perakitan',
        'is_include',
        'driver',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_active',
    ];

    protected $casts = [
        'tahun_perakitan' => 'integer',
        'is_include' => 'boolean',
        'is_active' => 'boolean',
    ];

    public $timestamps = false;

    public function tanggalSewa()
    {
        return $this->hasMany(TanggalDaftarMobilSewa::class, 'daftar_mobil_sewa_id');
    }

    public function tanggalSewaAktif()
    {
        return $this->hasMany(TanggalDaftarMobilSewa::class, 'daftar_mobil_sewa_id')
            ->where('is_active', true)
            ->orderBy('tanggal_sewa');
    }
}
