<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLainCO224Jam {
    public function index($data, $id_parameter, $mdl) {
        $totalNilai = 0;
        $jumlahElemen = 0;

        $ta = $data->pluck('suhu')->toArray();
        $pa = $data->pluck('tekanan_udara')->toArray();
        foreach ($data as $dataItem) {
            $pengukuran = json_decode($dataItem->pengukuran, true);
            foreach ($pengukuran as $value) {
                $totalNilai += floatval($value);
                $jumlahElemen++;
            }
        }

        $tekanan_udara = !empty($pa) ? round(array_sum($pa) / count($pa), 1) : 0;
        $suhu = !empty($ta) ? round(array_sum($ta) / count($ta), 1) : 0;

        $c1 = $c2 = $c3 = $c4 = $c5 = $c15 = $c16 = $c17 = NULL;
        $satuan = NULL;

        $c3 = round($totalNilai / $jumlahElemen, 6);
        $c2 = (($c3 * 44.01) / 24.45) * ($suhu / $tekanan_udara) * (298 / 760);
        $c1 = $c2 * 1000;
        $c4 = round($c3 * 1000, 6);
        $c5 = round($c3 / 10000, 6);
        $c15 = $c3;
        $c17 = round($c15 * 44.01 / 24.45, 6);
        $c16 = round($c17 * 1000, 6);
        
        // $c3 = $c3 < 1 ? "<1" : number_format($c3, 1);
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