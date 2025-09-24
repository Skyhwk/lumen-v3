<?php 

namespace App\HelpersFormula;


class GravimetriMLSS
{
    public function index($data, $id_parameter, $mdl){
		$rumus = number_format((($data->a - $data->b) * 1000) / $data->v, 4);
		
		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;   
    }
}