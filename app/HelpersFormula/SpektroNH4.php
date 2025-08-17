<?php

namespace App\HelpersFormula;

class SpektroNH4 {
    public function index($data, $id_parameter, $mdl) {
        $rumus = str_replace(",", "", number_format($data->nh3n * 1.288, 4));

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