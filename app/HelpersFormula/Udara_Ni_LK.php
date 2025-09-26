<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Udara_Ni_LK
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

        $C2 = null;
        $Vstd = null;

        $Vstd = $data->average_flow * $data->durasi;
        if ((int) $Vstd <= 0) {
            $C = 0;
            $Qs = 0;
            $C1 = 0;
        } else {
            $rawC = (($ks - $kb) * $data->vl * $data->st) / $Vstd;
            $rawC1 = $rawC / 1000;
            $rawC2 = $rawC1 * 24.45 / 58.6934;
            $C2 = \str_replace(",", "", number_format($rawC2, 4));
        }

        $processed = [
            'hasil1' => $C2,
            'satuan' => 'PPM'
        ];

        return $processed;
    }
}