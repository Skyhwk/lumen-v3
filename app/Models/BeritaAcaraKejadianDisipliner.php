<?php

namespace App\Models;

class BeritaAcaraKejadianDisipliner extends Sector
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'berita_acara_kejadian_disipliner';

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_kejadian' => 'date',
        'is_active' => 'boolean',
        'tahun_nomor' => 'integer',
        'urutan_nomor' => 'integer',
        'file_size' => 'integer',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }

    public function penandatangan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'penandatangan_karyawan_id');
    }
}
