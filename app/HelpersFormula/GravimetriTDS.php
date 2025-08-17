<?php

namespace App\HelpersFormula;

class GravimetriTDS
{
	public function index($data, $id_parameter, $mdl)
	{
		// dd($data);
		$rerata1 = ($data->bk_1 + $data->bk_2) / 2;
		$rerata2 = ($data->bki1 + $data->bki2) / 2;
		$rumus = number_format(((($rerata2 - $rerata1) * 1000) / $data->vs) * $data->fp, 4);
		$NaCl = $rumus;
		if (!is_null($mdl) && $rumus < $mdl) {
			$rumus = '<' . $mdl;
		}

		$data = [
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
	}
}