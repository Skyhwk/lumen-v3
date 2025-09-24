<?php

namespace App\HelpersFormula;

class MercuryPadatanHG
{
    public function index($data, $id_parameter, $mdl){
        if($id_parameter == 422) {  //MercuryPadatanHG
			$rumus = number_format($data->hp / 1000, 4);
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