<?php

namespace App\HelpersFormula;

class Kesadahan 
{
    public static function index($data, $id_parameter, $mdl) {
        $rumus = number_format((($data->vts * $data->b * 1000) / 25 ) * $data->fp, 4);

        if(!is_null($mdl) && $rumus < $mdl){
            $rumus = '<' . $mdl;
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