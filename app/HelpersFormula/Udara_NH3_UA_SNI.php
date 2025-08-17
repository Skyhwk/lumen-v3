<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Udara_NH3_UA_SNI
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
            $C = \str_replace(",", "", number_format(($ks / floatval($Vu)) * 1000, 4));
        }else {
            $C = 0;
        }

        if(!is_null($mdl) && $C < $mdl){
            $C = '<'.$mdl;
        }


        $processed = [
            'hasil1' => $C,
            'satuan' => "ug/Nm3"
        ];

        return $processed;
    }

}