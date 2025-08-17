<?php

namespace App\HelpersFormula;

class SpektroNO2
{
	public function index($data, $id_parameter, $mdl)
	{
		$rumus = number_format(($data->hp * 3.28443) * $data->fp, 4);
		if (!is_null($mdl) && $rumus < $mdl)
			$rumus = '<' . $mdl;
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