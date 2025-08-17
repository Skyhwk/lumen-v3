<?php

namespace App\HelpersFormula;

class SpektroPadatanNO2
{
    public function index($data, $id_parameter, $mdl)
    {
        $rumus = ($data->hp * 3.2845) * $data->fp;
        if (!is_null($mdl) && $rumus < $mdl)
            $rumus = '<' . $mdl;
        $rumus = str_replace(",", "", $rumus);

        $data = [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
        return $data;
    }
}