<?php 

namespace App\HelpersFormula;

class KMnO4 
{
    public function index($data, $id_parameter, $mdl) {
        $oksalat 	= 0.0100;
		$KMnO4 		= 31.6;
        $vts = $data->vts;
        $vtb = $data->vtb;
        $kt = $data->kt;
        $vs = $data->vs;
        $fp = $data->fp;

		// ((((10-A)B-(10*0.01))*1*31.6*1000)/100)*FP
		
		// $rumus = number_format(((ABS((( 10 - $vts ) * $vtb) - ( 10 * $oksalat )) * 1 * $KMnO4 * 1000) / 100) * $fp, 4);
		$rumus = number_format(((ABS((( 10 - $vts ) * $kt) - ( 10 * $oksalat )) * 1 * $KMnO4 * 1000) / 100) * $fp, 4);
		if(!is_null($mdl) && $rumus< $mdl){
			$rumus= '<' . $mdl;
		} else {
			$rumus = str_replace(",", "", $rumus);
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