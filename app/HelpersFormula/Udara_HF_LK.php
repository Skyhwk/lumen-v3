<?php

namespace App\HelpersFormula;

use Carbon\Carbon;
class Udara_HF_LK
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

        $Vs = \str_replace(",", "",number_format(($data->durasi * $data->average_flow) * (298/(273 + $data->suhu)) * ($data->tekanan/760), 4));
        if((int)$Vs <= 0) {
            $C = 0;
        }else {
            $C = \str_replace(",", "", number_format(((20/19)*($ks - $kb)* 12.5)/$Vs, 4));
        }

        if(!is_null($mdl) && $C < $mdl){
            $C = '<'.$mdl;
        }

        $data = [
            'hasil1' => $C,
            'satuan' => 'mg/Nm3'
        ];

        return $data;
    }

}