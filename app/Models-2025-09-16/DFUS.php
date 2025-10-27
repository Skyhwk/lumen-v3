<?php

namespace App\Models;

use App\Models\Sector;

class DFUS extends Sector
{
    protected $table = 'dfus';

    public function pelanggan()
    {
        return $this->belongsTo(MasterPelanggan::class, 'id_pelanggan', 'id_pelanggan')->where('is_active', true);
    }

    public function getLogWebphoneAttribute()
    {
        $masterKaryawan = MasterKaryawan::where('nama_lengkap', $this->sales_penanggung_jawab)->first();

        return LogWebphone::where('karyawan_id', $masterKaryawan->id)
            ->where('number', explode(' - ', $this->kontak)[1])
            ->whereDate('created_at', $this->tanggal)
            ->latest()
            ->get();
    }
}
