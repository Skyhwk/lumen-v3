<?php

namespace App\HelpersFormula;


class CO2
{
	public function index($data, $id_parameter, $mdl)
	{

		$rumus = number_format((($data->vtb * $data->kt * 44000) / $data->vts) * $data->fp, 4);
		// $rumus = number_format(($data->vts * $data->kt * 44000)/ $data->vtb, 4);

		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
	}
}