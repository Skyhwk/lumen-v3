<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

        $logs = LogWebphone::where('karyawan_id', $masterKaryawan->id)
            ->whereDate('created_at', $this->tanggal)
            ->latest();

        if (strpos($this->kontak, ' - ')) {
            $kontak = explode(' - ', $this->kontak)[1];

            $logs->where('number', 'like', '%' . $kontak . '%'); // ngindarin yg ga keformat kontaknya
        }

        return $logs->get();
    }
}
