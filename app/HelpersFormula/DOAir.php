<?php 

namespace App\HelpersFormula;
use Carbon\Carbon;

class DOAir
{
    public function index($data, $id_parameter, $mdl){
        $rumus = number_format($data->volume_titrasi_baru * 1, 4, '.', '');
		if(!is_null($mdl) && $rumus < $mdl){
            $rumus = '<' . $mdl;
        }
		
		$data = [
			'hasil' => $rumus,
			'hasil2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
    }
}