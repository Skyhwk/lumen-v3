<?php

namespace App\Models;

class BakInvestigasiBeritaAcara extends Sector
{
    public const UPDATED_AT = null;

    protected $table = 'bak_investigasi_berita_acara';

    protected $guarded = ['id'];

    protected $casts = [
        'bak_investigasi_id' => 'integer',
        'berita_acara_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function investigasi()
    {
        return $this->belongsTo(BakInvestigasi::class, 'bak_investigasi_id');
    }

    public function beritaAcara()
    {
        return $this->belongsTo(BeritaAcaraKejadianDisipliner::class, 'berita_acara_id');
    }
}
