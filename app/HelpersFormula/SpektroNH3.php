<?php

namespace App\HelpersFormula;

class SpektroNH3
{
	public function index($data, $id_parameter, $mdl)
	{
		// Sebelumnya 1.2158, Perubahan Sesuai Rekap WS
		$nilai_rumus = ($data->hp * 1.21589) * $data->fp;

		// Logika perbandingan pakai float dulu
		if (!is_null($mdl) && $nilai_rumus < $mdl) {
			$rumus = '<' . number_format($mdl, 4);
		} else {
			$rumus = number_format($nilai_rumus, 4);
		}
		$rumus = str_replace(",", "", $rumus);
		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => ''
		];
		return $data;
	}
}