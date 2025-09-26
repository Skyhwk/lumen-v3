<?php

namespace App\HelpersFormula;

class KalkulasiRSC
{
    public function index($data, $id_parameter, $mdl)
    {
        $rumus = number_format(($data->hco3 - $data->co3) - ($data->ca + $data->mg), 4);

        if(!is_null($mdl) && $rumus < $mdl){
            $rumus = "<" . $mdl;
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