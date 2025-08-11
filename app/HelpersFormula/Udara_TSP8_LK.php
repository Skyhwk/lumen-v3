<?php

namespace App\HelpersFormula;

class Udara_TSP8_LK {
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        // dd(count($data->ks));
        $kb = null;

        $w1 = null;
        $w2 = null;
        $b1 = null;
        $b2 = null;
        $V = null;
        $C = null;

        $C = \str_replace(",", "", number_format(((($data->w2 - $data->w1) - ($data->b2 - $data->b1)) / $V) * 1000, 6));
        $V = \str_replace(",", "",($data->average_flow * $data->durasi));
        
        if(!is_null($mdl) && $C < $mdl){
            $C = '<'.$mdl;
        }

        $processed = [
            'hasil1' => $C,
            'satuan' => 'mg/m3'
        ];

        return $processed;
    }
}