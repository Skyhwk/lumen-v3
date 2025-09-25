<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class Direct{
    public function index($data, $id_parameter, $mdl){
		$rumus = $data->hp;
        if(!is_null($mdl) && $rumus<$mdl)$rumus = '<' . $mdl;

		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
	}
}