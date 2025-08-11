<?php

namespace App\HelpersFormula;

class SpektroPadatanF
{
    public function index($data, $id_parameter, $mdl) {
        $rumus = (($data->hp / 50) * (60/50)) * $data->fp;
        if(!is_null($mdl) && $rumus < $mdl) $rumus = '<' . $mdl;
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