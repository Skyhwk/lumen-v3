<?php

namespace App\HelpersFormula;

class SO32Titrimetri
{
    public static function index($data,$id_parameter,$mdl)
    {
        $rumus = number_format((($data->vts - $data->vtb) * $data->m * 6 * 40000) / $data->vs, 4);

        if(!is_null($mdl) && $rumus < $mdl){
            $rumus = "<" . $mdl;
        }

        $rumus = str_replace(",", "", $rumus);

        $processed = [
            'hasil' => $rumus,
            'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
        ];
        
        return $processed;
    }
}