<?php

namespace App\HelpersFormula;

class Persistent
{
    public function index($data, $id_parameter, $mdl) {
        $rumus = number_format(($data->hp / 1000) / $data->waktu, 4);
		
		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
    }
}