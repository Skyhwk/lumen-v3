<?php

namespace App\HelpersFormula;

class Resistivity {
    public function index($data, $id_parameter, $mdl) {
        $rumus = number_format(1 / $data->hp, 4);
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