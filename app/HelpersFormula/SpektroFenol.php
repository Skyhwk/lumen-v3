<?php
namespace App\HelpersFormula;


class spektroFenol
{
    public function index($data, $id_parameter, $mdl){
		$rumus = number_format(($data->hp / 100) * 1000 * $data->fp, 4);

		// cek mdl
		if (!is_null($mdl) && is_numeric($rumus)) {

			$nilaiRumus = str_replace(',', '', $rumus);

			if ((float)$nilaiRumus < (float)$mdl) {
				$rumus = '<' . $mdl;
			}
		}

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