<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Udara_Pb_IKM_ICP_LK
{
    public function index($data, $id_parameter, $mdl)
    {
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

        $C = null;
        $Vstd = null;

        $Vstd = $data->average_flow * $data->durasi;
        if ((int) $Vstd <= 0) {
            $C = 0;
        } else {
            $rawC = (((($ks - $kb) * $data->vl * $data->st) / $Vstd) / 1000) * 24.45 / 207.2;
            $C = \str_replace(",", "", number_format($rawC, 8));
        }

        if (!is_null($mdl) && $C < $mdl) {
            $C = '<' . $mdl;
        }

        $processed = [
            'hasil1' => $C,
            'satuan' => 'mg/m3'
        ];

        return $processed;
    }
}