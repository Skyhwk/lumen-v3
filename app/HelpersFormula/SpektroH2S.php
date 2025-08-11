<?php

namespace App\HelpersFormula;

class SpektroH2S
{
	public function index($data, $id_parameter, $mdl)
	{
		$rumus = ($data->hp * 1.06) * $data->fp;
		if ($id_parameter == 72) {
			if ($rumus < 0.0020)
				$rumus = '<0.0020';
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