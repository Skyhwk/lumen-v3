<?php

namespace App\HelpersFormula;

class Persistent
{
    public function index($data, $id_parameter, $mdl) {
        // Cek jika $data->waktu bernilai 0 atau kosong, maka set $rumus jadi 0
		$waktu = (float) $data->waktu;
		$rumus = ($waktu == 0) ? 0 : number_format(($data->hp / 1000) / $waktu, 4);
		// $rumus2 = ($waktu == 0) ? 0 : number_format(($data->hp / $waktu, 4);


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