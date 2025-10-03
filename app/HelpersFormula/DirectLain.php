<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLain {
    public function index($data, $id_parameter, $mdl) {
        $totalNilai = 0;
        $jumlahElemen = 0;
        foreach ($data as $data) {
            $pengukuran = json_decode($data->pengukuran, true); // jadi array
            foreach ($pengukuran as $key => $value) {
                $totalNilai += floatval($value);
                $jumlahElemen++;
            }
        }
        $paramKoma2 = ["O2 (UA)", "O2 (LK)", "O2"];
        $paramMg = ["H2CO", "HCHO (8 Jam)", "VOC", "VOC (8 Jam)"];
        // $hasil2 = null; // naik setelah tanggal 10-10-2025
        // $hasil3 = null; // naik setelah tanggal 10-10-2025
        // $hasil4 = null; // naik setelah tanggal 10-10-2025
        if (in_array($data->parameter, $paramKoma2)) {
            $angkaBelakangKoma = 2;
            $satuan = '%';  
            // $hasil4 = $jumlahElemen > 0 ? number_format($totalNilai / $jumlahElemen, $angkaBelakangKoma, '.', ',') : 0; // naik setelah tanggal 10-10-2025
        } else {
            $angkaBelakangKoma = 4;
            if (in_array($data->parameter, $paramMg)) {
                $satuan = 'mg/m3';
                // $hasil2 = $jumlahElemen > 0 ? number_format($totalNilai / $jumlahElemen, $angkaBelakangKoma, '.', ',') : 0; // naik setelah tanggal 10-10-2025
            }else{
                $satuan = 'ppm';
                // $hasil3 = $jumlahElemen > 0 ? number_format($totalNilai / $jumlahElemen, $angkaBelakangKoma, '.', ',') : 0; // naik setelah tanggal 10-10-2025
            }
        }

        $average = $jumlahElemen > 0 ? number_format($totalNilai / $jumlahElemen, $angkaBelakangKoma, '.', ',') : 0; // mati setelah tanggal 10-10-2025
        return [
            'hasil' => $average,
            'satuan' => $satuan,
            // 'hasil2' => $hasil2, // naik setelah tanggal 10-10-2025
            // 'hasil3' => $hasil3, // naik setelah tanggal 10-10-2025
            // 'hasil4' => $hasil4 // naik setelah tanggal 10-10-2025
        ];

    }
}