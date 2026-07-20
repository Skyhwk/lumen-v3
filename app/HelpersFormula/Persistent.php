<?php

namespace App\HelpersFormula;

class Persistent
{
    public function index($data, $id_parameter, $mdl) {
        // Cek jika $data->waktu bernilai 0 atau kosong, maka set $rumus jadi 0
		$waktu = (float) $data->waktu;
		$hp = (float) $data->hp;
		$rumus = ($waktu == 0 || $hp == 0) ? 0 : number_format(($hp / 1000) / $waktu, 4);

		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
    }
}