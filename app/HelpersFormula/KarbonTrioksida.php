<?php

namespace App\HelpersFormula;

class KarbonTrioksida
{
    public function index($data, $id_parameter, $mdl) {
        $rumus = str_replace(",", "", number_format(((2 * ($data->p - $data->m)) * $data->n * 50000 / $data->v) * $data->fp, 4));

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