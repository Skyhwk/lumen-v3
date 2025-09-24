<?php

namespace App\HelpersFormula;

class SpektroPadatanAAS
{
    public function index($data, $id_parameter, $mdl)
    {
        $rumus = $data->hp * $data->fp;
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