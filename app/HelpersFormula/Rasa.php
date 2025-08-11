<?php

namespace App\HelpersFormula;

class Rasa {
    public function index($data, $id_parameter, $mdl)
	{
		if($data->hp == "Tidak_Berbau"){
			$rumus = "Tidak Berbau";
		}else{
			$rumus = number_format(((($data->hp + (200-$data->hp)))/$data->hp), 4, '.', '');
		}
		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
	}
}