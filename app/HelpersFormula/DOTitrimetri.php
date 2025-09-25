<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class DOTitrimetri
{
    public function index($data, $id_parameter, $mdl){
		if(isset($data->konsetrasi_titran)){
            // (A/0.025)* B * FP)
            $rumus = number_format(($data->konsetrasi_titran / 0.025) * $data->volume_titrasi * $data->fp, 4, '.', '');
        }else{
            // HP * FP
            $rumus = number_format($data->hp * $data->fp, 2, '.', '');
        }

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
