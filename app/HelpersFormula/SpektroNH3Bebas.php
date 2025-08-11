<?php

namespace App\HelpersFormula;

class SpektroNH3Bebas {
    public function index($data, $id_parameter, $mdl) {
        $rumus = str_replace(",", "", number_format($data->hp * $data->fp * 1.214, 4));

        if(!is_null($mdl) && $rumus< $mdl){
            $rumus= '<' . $mdl;
        }

        $processed = [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];

        return $processed;
    }
}