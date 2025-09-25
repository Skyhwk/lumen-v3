<?php 

namespace App\HelpersFormula; 
use Carbon\Carbon;

class CaAir 
{
    public function index($data, $id_parameter, $mdl){
        $rumus = (1000 / $data->vs) * $data->vts * $data->kt * 40;
        if(!is_null($mdl) && $rumus < $mdl){
            $rumus = '<' . $mdl;
        }

		$processed = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $processed;
        
    }
}