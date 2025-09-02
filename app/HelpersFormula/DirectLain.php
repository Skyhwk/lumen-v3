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
        $paramKoma2 = ["O2 (UA)", "O2 (LK)"];
        $paramMg = ["CO (mg-LK)"];
        if (in_array($data->parameter, $paramKoma2)) {
            $angkaBelakangKoma = 2;
            $satuan = '%';  
        } else {
            if (in_array($data->parameter, $paramMg)) {
                $satuan = 'mg/m3';
            }
            $angkaBelakangKoma = 4;
            $satuan = 'PPM';
        }

        $average = $jumlahElemen > 0 ? number_format($totalNilai / $jumlahElemen, $angkaBelakangKoma, '.', ',') : 0;
        return [
            'hasil' => $average,
            'satuan' => $satuan
        ];

    }
}