<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLainCO224Jam {
    public function index($data, $id_parameter, $mdl) {
        $totalNilai = 0;
        $jumlahElemen = 0;
        foreach ($data as $dataItem) {
            $pengukuran = json_decode($dataItem->pengukuran, true);
            foreach ($pengukuran as $value) {
                $totalNilai += floatval($value);
                $jumlahElemen++;
            }
        }

        $c1 = $c2 = $c3 = $c4 = $c5 = $c15 = $c16 = $c17 = NULL;
        $satuan = NULL;

        $c3 = round($totalNilai / $jumlahElemen, 1);
        $c2 = round(0.0409 * $c3 * 44.03, 4);
        $c1 = round($c2 * 1002, 4);
        $c4 = $c1;
        $c5 = round($c3 / 10000, 4);
        
        $c3 = $c3 < 1 ? "<1" : number_format($c3, 1);
        $c15 = $c3;
        $c17 = $c2;
        $c16 = $c17 * 1000;
        return [
            'c1'        => $c1,
            'c2'        => $c2,
            'c3'        => $c3,
            'c4'        => $c4,
            'c5'        => $c5,
            'c15'       => $c15,
            'c16'       => $c16,
            'c17'       => $c17,
            'satuan'    => 'ppm'
        ];
    }
}