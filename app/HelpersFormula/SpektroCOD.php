<?php 

namespace App\HelpersFormula;

class spektroCOD
{
    public function index($data, $id_parameter, $mdl){
		// dd($hp, $fp);
		$rumus = (($data->hp * 1000) / 2.5) * $data->fp;
		if(!is_null($mdl) && $rumus < $mdl)$rumus = '<' . $mdl;
		// Format hanya jika tidak mengandung '<'
		$rumus = (strpos($rumus, '<') === false) ? number_format($rumus, 4) : $rumus;
		$rumus = str_replace(",", "", $rumus);
        
		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		// dd($data);
		return $data;
	}
}