<?php

namespace App\HelpersFormula;

class NaClTitrimetri
{
    public function index($data, $id_parameter, $mdl)
    {
        // Rumus mirip dengan pada file CloridaAir bedanya ada NaCl
        
        $rumus = number_format(((($data->vts - $data->vtb) * $data->kt * 35450) / 100) * $data->fp, 4);
        $nilai = floatval(str_replace(",", "", $rumus));
        $NaCl = number_format($nilai * 1.65, 4);
        
        if (!is_null($mdl) && $NaCl < $mdl) {
            $NaCl = '<' . $mdl;
        } else {
            $NaCl = str_replace(",", "", $NaCl);
        }
        
        return [
            'hasil' => $NaCl,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
    }
}