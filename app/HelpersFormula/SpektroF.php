<?php 

namespace App\HelpersFormula;


class spektroF{
    public function index($data, $id_parameter, $mdl){
		// Sebelumnya 50 / 50
		$rumus = number_format((($data->hp / 50) * (50 / 50)) * $data->fp,4);
		if(!is_null($mdl) && $rumus<$mdl) {
			$rumus = '<' . $mdl;
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