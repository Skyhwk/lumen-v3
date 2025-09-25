<?php

namespace App\HelpersFormula;

class spektroMbas
{
	public function index($data, $id_parameter, $mdl)
	{
		$rumus = number_format(($data->hp / 100) * $data->fp, 4);
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