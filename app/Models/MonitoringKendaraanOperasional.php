<?php

namespace App\Models;

class MonitoringKendaraanOperasional extends Sector
{
    protected $table = 'monitoring_kendaraan_operasional';

    public $timestamps = false;

    protected $fillable = [
        'daftar_mobil_id',
        'nama_mobil',
        'plat_mobil',
        'code_aset',
        'jenis_dimensi_kendaraan',
        'tipe_transmisi',
        'kepemilikan',
        'durasi_ketersediaan',
        'status_kendaraan',
        'keterangan',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'durasi_ketersediaan' => 'integer',
    ];

    public const STATUS_TERSEDIA = 'TERSEDIA';
    public const STATUS_RUSAK = 'RUSAK';
    public const STATUS_SEDANG_DI_MAINTENANCE = 'SEDANG_DI_MAINTENANCE';
    public const STATUS_SEDANG_DIBOOKING = 'SEDANG_DIBOOKING';
    public const STATUS_SEDANG_DI_GUNAKAN = 'SEDANG_DI_GUNAKAN';

    public const TAB_TERSEDIA = 'tersedia';
    public const TAB_SEDANG_DIGUNAKAN = 'sedang_digunakan';

    public function daftarMobil()
    {
        return $this->belongsTo(DaftarMobil::class, 'daftar_mobil_id');
    }
}
