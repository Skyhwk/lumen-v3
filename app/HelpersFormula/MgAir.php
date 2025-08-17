<?php 

namespace App\HelpersFormula;

class MgAir
{
    public function index($data, $id_parameter, $mdl){
        $rumus = (1000 / $data->vs) * ABS($data->vts - $data->vtb) * $data->kt * 24.3;
		if(!is_null($mdl) && $rumus < $mdl){
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