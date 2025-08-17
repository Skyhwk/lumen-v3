<?php 

namespace App\HelpersFormula;


class N_Organik
{
    public function index($data, $id_parameter, $mdl){
		// Kadar N-Organik (mg/L) = (Vd*C*Fp)/Vc
		// C (mg/L) = ((A-B)*N*14*1000)/V
        $c = number_format((($data->volume_titrasi_d - $data->volume_titrasi_b) * $data->normalitas * 14 * 1000) / $data->volume_destilat, 4);
		$rumus = number_format(($data->volume_destilat_amonia * $c * $data->fp)/ $data->volume_contoh, 4);
		
		$data = [
			'hasil' => $rumus,
			'hasil_2' => $c,
			'rpd' => '',
			'recovery' => '',
		];
		return $data;   
    }
}