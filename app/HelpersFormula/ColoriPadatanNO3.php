<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class ColoriPadatanNO3
{
    public function index($data, $id_parameter, $mdl){
        $rumus = ($data->hp * 4.42664) * $data->fp;

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
