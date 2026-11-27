<?php

namespace App\Models;

class BakInvestigasi extends Sector
{
    protected $table = 'bak_investigasi';

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_kejadian' => 'date',
        'tgl_bak_pertama' => 'date',
        'tgl_bak_terakhir' => 'date',
        'bukti_pendukung' => 'array',
        'sanksi_st' => 'boolean',
        'sanksi_sp' => 'boolean',
        'sanksi_ganti_rugi' => 'boolean',
        'sanksi_lainnya' => 'boolean',
        'is_active' => 'boolean',
        'jumlah_bukti' => 'integer',
        'file_size' => 'integer',
        'creator_karyawan_id' => 'integer',
        'submitted_by_karyawan_id' => 'integer',
        'sanksi_surat_teguran_id' => 'integer',
        'sanksi_surat_peringatan_id' => 'integer',
        'submitted_at' => 'datetime',
        'legal_approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function pihak()
    {
        return $this->hasMany(BakInvestigasiPihak::class, 'bak_investigasi_id');
    }

    public function beritaAcaraLinks()
    {
        return $this->hasMany(BakInvestigasiBeritaAcara::class, 'bak_investigasi_id');
    }

    public function beritaAcara()
    {
        return $this->belongsToMany(
            BeritaAcaraKejadianDisipliner::class,
            'bak_investigasi_berita_acara',
            'bak_investigasi_id',
            'berita_acara_id'
        )->withPivot('id', 'created_at');
    }
}
