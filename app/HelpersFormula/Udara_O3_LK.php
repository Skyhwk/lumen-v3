<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Udara_O3_LK
{
    public function index($data, $id_parameter, $mdl) {
        $ks = $data->ks;
        $kb = $data->kb;

        $Ta = floatval($data->suhu) + 273;

        $C_value = [];
        $C1_value = [];
        $C2_value = [];
        foreach ($data->average_flow as $key => $value) {
            $Vu = \str_replace(",", "",number_format($value * $data->durasi[$key] * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
            if($Vu != 0.0) {
                $C = \str_replace(",", "", number_format(($ks[$key] / floatval($Vu)) * 1000, 4));
            }else {
                $C = 0;
            }
            $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
            $C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 48, 5));
            
            array_push($C_value, $C);
            array_push($C1_value, $C1);
            array_push($C2_value, $C2);
        }
        
        $avg_C = array_sum($C_value) / count($C_value);
        $avg_C1 = array_sum($C1_value) / count($C1_value);
        $avg_C2 = array_sum($C2_value) / count($C2_value);

        if (floatval($C1_value[0]) < 0.00014)
            $C1_value[0] = '<0.00014';
        if (floatval($C1_value[1]) < 0.00014)
            $C1_value[1] = '<0.00014';
        if (floatval($avg_C1) < 0.00014)
            $avg_C1 = '<0.00014';
        
        $processed = [
            'hasil1' => $C1_value[0],
            'hasil2' => $C1_value[1],
            'hasil3' => $avg_C1,
            'satuan' => 'mg/m³'
        ];

        return $processed;
    }

}