<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class Alkalinitas 
{
    public function index($data, $id_parameter, $mdl){
        $rumus = number_format((($data->vts * $data->kt * 50000)/ $data->vs) * $data->fp, 4);
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