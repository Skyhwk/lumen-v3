<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class DirectDTL
{
	public function index($data, $id_parameter, $mdl)
	{
		$rumus = number_format(1 / $data->hp, 4);
		if (!is_null($mdl) && $rumus < $mdl)
			$rumus = '<' . $mdl;
		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
	}
}