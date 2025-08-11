<?php

namespace App\HelpersFormula;

class Udara_Mn_LK {
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        if (is_array($data->ks)) {
            $ks = number_format(array_sum($data->ks) / count($data->ks), 4);
        } else {
            $ks = $data->ks;
        }
        $kb = null;
        if (is_array($data->kb)) {
            $kb = number_format(array_sum($data->kb) / count($data->kb), 4);
        } else {
            $kb = $data->kb;
        }

        $processed = [
            'hasil1' => $C,
            'satuan' => 'mg/Nm3'
        ];

        return $processed;
    }
}