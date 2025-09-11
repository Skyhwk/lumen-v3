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

        $logs = LogWebphone::where('karyawan_id', $masterKaryawan->id)
            ->whereDate('created_at', $this->tanggal)
            ->latest();

        if (strpos($this->kontak, ' - ')) {
            $kontak = explode(' - ', $this->kontak)[1];

            $logs->where('number', 'like', '%' . $kontak . '%'); // ngindarin yg ga keformat kontaknya
        }

        return $logs->get();
    }

    // public function getLogWebphoneAttribute()
    // {
    //     $masterKaryawan = MasterKaryawan::where('nama_lengkap', $this->sales_penanggung_jawab)->first();

    //     $logs = LogWebphone::where('karyawan_id', $masterKaryawan->id)
    //         ->whereDate('created_at', $this->tanggal)
    //         ->latest();

    //     if (strpos($this->kontak, ' - ')) {
    //         $kontak = explode(' - ', $this->kontak)[1];

    //         // Hapus semua karakter selain angka
    //         $kontak = preg_replace('/[^\d]/', '', $kontak);

    //         // Standarisasi ke format '08...'
    //         if (substr($kontak, 0, 2) === '62') {
    //             $kontak = '0' . substr($kontak, 2);
    //         } elseif (substr($kontak, 0, 1) !== '0') {
    //             $kontak = '0' . $kontak;
    //         }

    //         $logs->where('number', $kontak);
    //     }

    //     return $logs->get();
    // }
}
