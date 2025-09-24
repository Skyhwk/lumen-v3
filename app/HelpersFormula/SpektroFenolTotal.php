<?php
namespace App\HelpersFormula;


class spektroFenolTotal
{
	public function index($data, $id_parameter, $mdl)
	{
		$rumus = number_format(($data->hp / 100) * 1000 * $data->fp, 4);

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