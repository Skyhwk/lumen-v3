<?php

namespace App\Models;

class BakInvestigasiPihak extends Sector
{
    public const PERAN_PELAPOR = 'pelapor';
    public const PERAN_TERLAPOR = 'terlapor';
    public const PERAN_SAKSI = 'saksi';
    public const PERAN_LAINNYA = 'lainnya';

    protected $table = 'bak_investigasi_pihak';

    protected $guarded = ['id'];

    protected $casts = [
        'bak_investigasi_id' => 'integer',
        'id_karyawan' => 'integer',
        'urutan' => 'integer',
    ];

    public function investigasi()
    {
        return $this->belongsTo(BakInvestigasi::class, 'bak_investigasi_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan');
    }
}
