<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLainCoPmLk {

    public function index($data, $id_parameter, $mdl) {
        $hasilPerShift = [];
        $satuan = 'ppm';

        foreach ($data as $item) {
            $pengukuran = json_decode($item->pengukuran, true); // array dari 5 data pengukuran per shift

            $totalShift = 0;
            $jumlahPerShift = 0;

            foreach ($pengukuran as $value) {
                $totalShift += floatval($value);
                $jumlahPerShift++;
            }

            // Hitung rata-rata per shift
            if ($jumlahPerShift > 0) {  
                $rata2Shift = $totalShift / $jumlahPerShift;

                // Kalikan dengan faktor koreksi
                $hasilKoreksi = $rata2Shift;

                // Simpan hasil koreksi ke array
                $hasilPerShift[] = $hasilKoreksi;
            }
        }

        // Hitung total dan rata-rata akhir dari seluruh shift
        $totalFinal = array_sum($hasilPerShift);
        $jumlahShift = count($hasilPerShift);

        $hasil = $totalFinal / $jumlahShift;
        $hasil = number_format($hasil, 4, '.', ',');

        if($mdl){
            $hasil = $hasil < $mdl ? "<$mdl" : $hasil;
        };

        if($data->parameter == "VOC"){
            $satuan = "mg/m3";
        }

        return [
            'hasil' => $hasil,
            'satuan' => $satuan
        ];
    }
}