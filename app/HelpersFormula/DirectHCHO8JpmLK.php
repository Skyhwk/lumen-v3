<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectHCHO8JpmLK {
    public function index($data, $id_parameter, $mdl) {
        
        $measurements = [];
        $pa = $data->pluck('suhu')->toArray();
        $ta = $data->pluck('tekanan_udara')->toArray();
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

        $average = !empty($measurements) ?
            number_format(array_sum($measurements) / count($measurements), 3) : 0;
        $suhu = !empty($pa) ? round(array_sum($pa) / count($pa), 1) : 0;
        $tekanan_udara = !empty($ta) ? round(array_sum($ta) / count($ta), 1) : 0;

        $c1 = $c2 = $c3 = $c4 = $c5 = $c15 = $c16 = $c17 = NULL;
            
        // $c2 = round($average, 4); // mg/m³ 
        $c2 = round($average * ($suhu / $tekanan_udara) * (298 / 760), 4); // mg/m³
        $c1 = round($c2 * 1000, 4); // ug/Nm³ misalnya
        $c3 = number_format((24.45 / $average) * 30.03, 3);
        $c15 = $c3; // BDS
        $c17 = round($average, 4); // mg/m³
        $c16 = round($c17 * 1000, 4); // ug/Nm³ misalnya
        // $c3 = number_format((24.45*$average)/30.03, 3);
        
        // if (!is_null($mdl) && $hasil < $mdl) {
        //     $hasil = "<$mdl";
        // }

        return [
            'c1'     => $c1,
            'c2'     => $c2,
            'c3'     => $c3,
            'c4'     => $c4,
            'c5'     => $c5,
            'c15'    => $c15,
            'c16'    => $c16,
            'c17'    => $c17,
            'satuan' => 'mg/m3'
        ];
    }
}