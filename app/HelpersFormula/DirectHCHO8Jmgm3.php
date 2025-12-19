<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectHCHO8Jmgm3 {
    public function index($data, $id_parameter, $mdl) {
        
        $measurements = [];
        $ta = $data->pluck('suhu')->toArray();
        $pa = $data->pluck('tekanan_udara')->toArray();
        foreach ($data as $record) {
            if ($record->pengukuran) {
                $data = json_decode($record->pengukuran, true);
                if (is_array($data) && !empty($data)) {
                    $total = array_sum($data);
                    $count = count($data);
                    $measurements[] = $total / $count;
                }
            }
        }

        if(count($measurements) > 1){
            $average = !empty($measurements) ? number_format(array_sum($measurements) / count($measurements), 3) : 0;
        } else {
            $average = !empty($measurements) ? $measurements[0] : 0;
        }
        $tekanan_udara = !empty($pa) ? round(array_sum($pa) / count($pa), 1) : 0;
        $suhu = !empty($ta) ? round(array_sum($ta) / count($ta), 1) : 0;

        $c1 = $c2 = $c3 = $c4 = $c5 = $c15 = $c16 = $c17 = NULL;
        
        // $c2 = round($average * ($suhu / $tekanan_udara) * (298 / 760), 4); // mg/m³
        // $c1 = round($c2 * 1000, 4); // ug/Nm³ 
        // $c3 = round(($c2 / 30.03) * 24.45, 4); // mg/m³
        // $c15 = $c3; // BDS
        // $c17 = round($average, 4); // mg/m³
        // $c16 = round($c17 * 1000, 4); // ug/m³ 
        // $satuan = 'mg/Nm3';

        // FUNCTION POTONG DESIMAL (TANPA PEMBULATAN)
        function truncate($value, $decimal = 4)
        {
            $factor = pow(10, $decimal);
            return floor($value * $factor) / $factor;
        }

        // HITUNG NILAI MENTAH
        $c2_raw  = $average * ($suhu / $tekanan_udara) * (298 / 760); // mg/Nm³
        $c1_raw  = $c2_raw * 1000;                                  // ug/Nm³
        $c3_raw  = ($c2_raw / 30.03) * 24.45;                       // mg/m³
        $c17_raw = $average;                                        // mg/m³
        $c16_raw = $c17_raw * 1000;                                 // ug/m³

        // POTONG (BUKAN ROUND)
        $c2  = truncate($c2_raw, 4);
        $c1  = truncate($c1_raw, 4);
        $c3  = truncate($c3_raw, 4);
        $c15 = $c3;
        $c17 = truncate($c17_raw, 4);
        $c16 = truncate($c16_raw, 4);

        $satuan = 'mg/Nm3';


        return [
            'c1'     => $c1,
            'c2'     => $c2,
            'c3'     => $c3,
            'c4'     => $c4,
            'c5'     => $c5,
            'c15'    => $c15,
            'c16'    => $c16,
            'c17'    => $c17,
            'satuan' => $satuan
        ];
    }
}