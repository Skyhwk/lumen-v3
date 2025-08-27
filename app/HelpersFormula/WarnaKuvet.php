<?php

namespace App\HelpersFormula;

class WarnaKuvet
{
    public static function index($data, $id_parameter, $mdl)
    {
        $rumus = number_format(($data->hp / $data->lebar_kuvet) * 1000, 4, ".", "");

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
