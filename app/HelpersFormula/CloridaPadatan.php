<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class CloridaPadatan
{
    public function index($data, $id_parameter, $mdl)
    {
        $rumus = number_format((($data->vts - $data->vtb) * $data->kt * 35450) / 100, 2);
        $nilai = floatval(str_replace(",", "", $rumus));
        $NaCl = number_format($nilai * 1.65, 2);
        
        if (!is_null($mdl) && $nilai < $mdl) {
            $rumus = '<' . $mdl;
        } else {
            $rumus = str_replace(",", "", $rumus);
        }
        
        return [
            'hasil' => $rumus,
            'hasil_2' => $NaCl,
            'rpd' => '',
            'recovery' => '',
        ];
    }
}