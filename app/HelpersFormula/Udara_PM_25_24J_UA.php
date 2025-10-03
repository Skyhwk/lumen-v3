<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupPM_TSP
{
    public function index($data, $id_parameter, $mdl)
    {
        $C = null;
        $Vstd = null;

        $Vstd = \str_replace(",", "", number_format($data->nilQs * $data->durasi, 4));
        if ((int) $Vstd <= 0) {
            $C = 0;
        } else {
            $C = \str_replace(",", "", number_format((($data->w2 - $data->w1) * 10 ** 6) / $Vstd, 4));
        }

        if (!is_null($mdl) && $C < $mdl) {
            $C = '<' . $mdl;
        }

        $data = [
            'hasil1' => $C,
            'satuan' => 'µg/Nm3'
        ];

        return $data;
    }

}