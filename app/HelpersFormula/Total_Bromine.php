<?php

namespace App\HelpersFormula;

class Total_Bromine
{
    public function index($data, $id_parameter, $mdl)
    {
        $sisa_bromine = $data->hp_sisa_bromine * $data->fp_sisa_bromine;
        $sisa_cl2 = $data->hp_sisa_cl2 * $data->fp_sisa_cl2;
        $rumus = number_format($sisa_bromine + $sisa_cl2, 4);

        if (!is_null($mdl) && $rumus < $mdl) {
            $rumus = '<' . $mdl;
        }

        $rumus = str_replace(',', '', $rumus);

        $processed = [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];

        return $processed;
    }
}