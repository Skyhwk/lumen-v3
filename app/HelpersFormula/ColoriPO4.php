<?php

namespace App\HelpersFormula;

class ColoriPO4
{
    public function index($data, $id_parameter, $mdl) {
		$nilai_rumus = ($data->hp * 0.3261) * $data->fp;
	
		// Logika perbandingan pakai float dulu
		if (!is_null($mdl) && $nilai_rumus < $mdl) {
			$rumus = '<' . $mdl;
		} else {
			$rumus = number_format($nilai_rumus, 2);
		}
		$rumus = str_replace(",", "", $rumus);
		
		$processed = [
			'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',  
            'recovery' => ''
		];
		return $processed;
    }
}