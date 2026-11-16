<?php

namespace App\HelpersFormula;

class Gravimetri
{
	public function index($data, $id_parameter, $mdl)
	{
		$rerata1 = ($data->bk_1 + $data->bk_2) / 2;
		$rerata2 = ($data->bki1 + $data->bki2) / 2;
		$hasil = ((($rerata2 - $rerata1) * 1000) / $data->vs) * $data->fp;

		$rumus = number_format(round($hasil, 3), 4, '.', '');
		$NaCl = $rumus;

		if (!is_null($mdl) && $rumus < $mdl) {
			$rumus = '<' . $mdl;
		}

		$rumus = str_replace(',', '', $rumus);
		$data = [
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
	}
}