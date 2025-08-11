<?php 

namespace App\HelpersFormula;


class SpektroCr 
{
    public function index($data, $id_parameter, $mdl){
		// Ganti dari 2 angka dibelakang koma menjadi 4
		// Perubahan Sesuai Rekap WS
		$rumus = number_format(($data->hp / 50) * $data->fp, 4);
		if(!is_null($mdl) && $rumus < $mdl){
			$hg = '<' . $mdl;
		}else{$hg = $rumus;}
		$rumus = str_replace(",", "", $rumus);
        
		$data = [
			'hasil' => $hg,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
	}
}