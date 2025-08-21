<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganKerjaPertukaranUdara{
    public function index($data, $id_parameter, $mdl) {
        $hasilGabung = [];

        // 1. Kumpulkan semua nilai dari setiap data_lapangan
        foreach ($data->data_lapangan as $lapangan) {
            if (!empty($lapangan->pengukuran)) {
                $pengukuran = json_decode($lapangan->pengukuran, true);

                foreach ($pengukuran as $key => $value) {
                    if (is_array($value)) {
                        $hasilGabung[$key] = array_merge($hasilGabung[$key] ?? [], $value);
                    } else {
                        $hasilGabung[$key][] = $value;
                    }
                }
            }
        }

        foreach ($hasilGabung as $key => $values) {
            $angka = array_map(function($v) {
                // Pecah berdasarkan spasi, ambil bagian pertama
                $parts = explode(' ', trim($v));
                return floatval($parts[0]);
            }, $values);

            ${$key} = count($angka) > 0 ? array_sum($angka) / count($angka) : 0;

            // Kalau mau hasilGabung tanpa satuan
            $hasilGabung[$key] = $angka;
        }

        $avgVolume = 0;
        $avgPanjang = 0;
        $avgLebar = 0;
        $avgTinggi = 0;
        $avgJumlah_pengukuran = 0;
        $avgLuas = 0;
        $avgLaju_ventilasi = 0;

        if (!empty($hasilGabung['volume_ruangan'])) {
            $values = $hasilGabung['volume_ruangan'];
            $avgVolume = array_sum($values) / count($values);
        }

        if (!empty($hasilGabung['panjang_ruangan'])) {
            $values = $hasilGabung['panjang_ruangan'];
            $avgPanjang = array_sum($values) / count($values);
        }

        if (!empty($hasilGabung['lebar_ruangan'])) {
            $values = $hasilGabung['lebar_ruangan'];
            $avgLebar = array_sum($values) / count($values);
        }

        if (!empty($hasilGabung['tinggi_ruangan'])) {
            $values = $hasilGabung['tinggi_ruangan'];
            $avgTinggi = array_sum($values) / count($values);
        }

        if (!empty($hasilGabung['jumlah_pengukuran'])) {
            $values = $hasilGabung['jumlah_pengukuran'];
            $avgJumlah_pengukuran = array_sum($values) / count($values);
        }

        if (!empty($hasilGabung['luas_penampang'])) {
            $values = $hasilGabung['luas_penampang'];
            $avgLuas = array_sum($values) / count($values);
        }

        if (!empty($hasilGabung['laju_ventilasi'])) {
            $values = $hasilGabung['laju_ventilasi'];
            $avgLaju_ventilasi = array_sum($values) / count($values);
        }

        
        $a= $avgPanjang * $avgLebar * $avgTinggi;
        $b = $avgJumlah_pengukuran * $avgLaju_ventilasi * $avgLuas;

        $c = $a / $b;
        $c = number_format($c, 1, '.', '');

        return [
            'hasil' => $c,
            'satuan' => 'Kali/Jam'
        ];
    }
}