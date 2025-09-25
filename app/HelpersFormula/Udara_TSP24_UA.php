<?php

namespace App\HelpersFormula;

class Udara_TSP24_UA {
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        // dd(count($data->ks));
        $kb = null;

        $Ta = floatval($data->suhu) + 273;
        $Qs = null;
        $C = null;

        $Vstd = \str_replace(",", "",number_format($data->nilQs * $data->durasi, 4));
        if((int)$Vstd <= 0) {
            $C = 0;
            $Qs = 0;
        }else {
            $C = \str_replace(",", "", number_format((($data->w2 - $data->w1) * 10 ** 6) / $Vstd, 4));
            $Qs = $data->nilQs;
        }
        
        if(!is_null($mdl) && $C < $mdl){
            $C = '<'.$mdl;
        }

        $processed = [
            'hasil1' => $C,
            'satuan' => 'µg/Nm3'
        ];

        return $processed;
    }
}