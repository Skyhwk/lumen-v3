<?php

namespace App\HelpersFormula;

class SulfiteAir
{
	public function index($data, $id_parameter, $mdl)
	{
		$rumus = number_format((($data->vts - $data->vtb) * $data->kt * 6 * 40000) / $data->vs, 4);
		if (!is_null($mdl) && $rumus < $mdl) {
			$rumus = '<' . $mdl;
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