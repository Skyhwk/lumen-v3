<?php

namespace App\Models;

use App\Models\Sector;

class TanggalDaftarMobilSewa extends Sector
{
    protected $table = 'tanggal_daftar_mobil_sewa';

    protected $fillable = [
        'daftar_mobil_sewa_id',
        'tanggal_sewa',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_active',
    ];

    protected $casts = [
        'tanggal_sewa' => 'date',
        'is_active' => 'boolean',
    ];

    public $timestamps = false;

    public function mobilSewa()
    {
        return $this->belongsTo(DaftarMobilSewa::class, 'daftar_mobil_sewa_id');
    }
}
