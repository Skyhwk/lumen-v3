<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Udara_H2S_LK
{
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
        $Qs = null;
        $C = null;

        $Vu = \str_replace(",", "",number_format($data->average_flow * $data->durasi * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
        if($Vu != 0.0) {
            $C_ = \str_replace(",", "", number_format(($ks - $kb) / floatval($Vu), 4));
        }else {
            $C_ = 0;
        }
        
        $C_ = \str_replace(",", "", number_format((floatval($ks) - floatval($kb)) / (floatval($Vu) != 0.0 ? floatval($Vu) : 1), 4));
        $C = \str_replace(",", "", number_format(floatval($C_) * (34 / 24.45), 4));
        $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));

        if(!is_null($mdl) && $C1 < $mdl){
            $C1 = '<'.$mdl;
        }

        $processed = [
            'hasil' => $C1,
            'satuan' => 'mg/m3',
        ];

        return $processed;
    }

}