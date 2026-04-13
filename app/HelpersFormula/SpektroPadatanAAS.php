<?php

namespace App\HelpersFormula;

class SpektroPadatanAAS
{
    public function index($data, $id_parameter, $mdl)
    {
        $rumus = number_format($data->hp * $data->fp, 5, '.', '');
        if (!is_null($mdl) && $rumus < $mdl)
            $rumus = '<' . $mdl;

        $data = [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
        return $data;
    }
}
