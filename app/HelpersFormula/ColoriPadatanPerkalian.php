<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class ColoriPadatanNO3
{
    public function index($data, $id_parameter, $mdl){
        $rumus = number_format($data->hp * $data->fp, 4);
		if($rumus < $mdl){
            $rumus = '<' . $mdl;
        }   

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