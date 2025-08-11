<?php 

namespace App\HelpersFormula;


class GravimetriMLVSS
{
    public function index($data, $id_parameter, $mdl){
        
		$volatil_solid = number_format((($data->a - $data->b) * 1000) / $data->v, 4);
		$rumus = number_format((($data->b - $data->c) * 1000) / $data->v, 4);
		
		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;   
    }
}