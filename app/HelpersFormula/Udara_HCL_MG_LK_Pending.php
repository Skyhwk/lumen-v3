<?php

namespace App\HelpersFormula;

class Udara_HCL_MG_LK {
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        // dd(count($data->ks));
        if (is_array($data->ks)) {
            $ks = number_format(array_sum($data->ks) / count($data->ks), 4);
        }else {
            $ks = $data->ks;
        }
        $kb = null;
        if (is_array($data->kb)) {
            $kb = number_format(array_sum($data->kb) / count($data->kb), 4);
        }else {
            $kb = $data->kb;
        }

        $Ta = floatval($data->suhu) + 273;
        $Vs = null;
        $C = null;
        $C1 = null;

        $Vs = \str_replace(",", "",number_format(($data->durasi * $data->average_flow) * (298/(273 + $data->suhu)) * ($data->tekanan/760), 4));
        $C1 = \str_replace(",", "", number_format(((($ks - $kb)* 50 * (36.5/35.5))/$Vs) * 1000, 4)); // Belum Ada
        $C = \str_replace(",", "", number_format(24.45*($C1/36.5), 4));

        $processed = [
            'hasil1' => $C,
            'satuan' => 'mg/m3'
        ];

        return $processed;
    }
}