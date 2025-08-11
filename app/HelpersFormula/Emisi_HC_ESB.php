<?php

namespace App\HelpersFormula;

class Emisi_HC_ESB 
{
    public function index($data, $id_parameter, $mdl)
    {
        $C = null;

        if (is_array($data->C) && !empty($data->C)) {
            $total = array_sum($data->C);
            $count = count($data->C);
            $C = $total / $count;
        } else {
            $C = 0;
        }

        return [
            'hasil1' => $C,
            'satuan' => '%'
        ];
    }
}