<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupCl2
{
    public function index($data, $id_parameter, $mdl)
    {
        $ks = null;
        if (is_array($data->ks)) {
            $ks = array_sum($data->ks) / count($data->ks);
        } else {
            $ks = $data->ks;
        }

        $Ta = floatval($data->suhu) + 273;
        $C = null;
        $Vu = null;

        $Vu = ($data->average_flow) * $data->durasi * ($data->tekanan / $Ta) * (298 / 760);
        if ((int) $Vu <= 0) {
            $C = 0;
        } else {
            $C = \str_replace(",", "", number_format(($ks / $Vu) * 1000000, 4));
        }

        if (!is_null($mdl) && $C < $mdl) {
            $C = '<' . $mdl;
        }

        $data = [
            'hasil1' => $C,
            'satuan' => 'ug/Nm3'
        ];

        return $data;
    }

}