<?php

namespace App\HelpersFormula;

class Emisi_Opasitas_ESTB
{
    public function index($data, $id_parameter, $mdl)
    {
        $C = null;
        if (is_array($data->C) && !empty($data->C)) {
            $C = array_sum($data->C) / count($data->C);
        } else {
            $C = 0;
        }

        return [
            'hasil1' => $C,
            'satuan' => '%'
        ];
    }
}