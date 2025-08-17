<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class ColoriNO3
{
	public function index($data, $id_parameter, $mdl)
	{
		$rumus = number_format(($data->hp * 4.42664) * $data->fp, 4);
		if ($rumus < $mdl)
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