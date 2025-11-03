<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectpartikulatPM {
    public function index($data, $id_parameter, $mdl) {
        $measurements = [];

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

        $ta = $data->pluck('suhu')->toArray();
        $pa = $data->pluck('tekanan_udara')->toArray();

        $tekanan_udara = !empty($pa) ? round(array_sum($pa) / count($pa), 1) : 0;
        $suhu = !empty($ta) ? round(array_sum($ta) / count($ta), 1) : 0;

        $avg = !empty($measurements) ? array_sum($measurements) / count($measurements) : 0;
        $c16 = round($avg, 4); // ug/m³
        $c17 = round($c16 * 1000, 4); // mg/m³
        $c1 = round($c16 * (($tekanan_udara / $suhu) * (298 / 760)), 4); // ug/Nm³ misalnya
        $c2 = round($c1 / 1000, 4); // mg/Nm³ 
        // if ($id_parameter == 311 || $id_parameter == 314) { // PM 10 atau PM 2.5 24 Jam
        //     $c2 = $c2 < 0.0631 ? '<0.0631' : $c2; //mg/Nm3
        // }

        return [
            'c1'        => $c1,
            'c2'        => $c2,
            'c16'       => $c16,
            'c17'       => $c17,
            'satuan'    => 'ug/Nm3'
        ];
    }
}