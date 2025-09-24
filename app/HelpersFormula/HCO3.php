<?php

namespace App\HelpersFormula;

class HCO3 {
    public function index($data, $id_parameter, $mdl){
        $t = $data->vtm;
        $p = $data->vtp;
        $kt = $data->kt;
        $vs = $data->vs;
        $ph = $data->ph;

        $half = $t * 50 / 100;

		if($p == 0){
			$data->vth = 0;
			$co3 = 0;
			$t_convert = $t;

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if ($p < $half){
			$data->vth = 0;
			$co3 = 2 * $p;
			$t_convert = (($t - (2 * $p)) + (pow(10, ($ph - 14))));

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if ($p == $half){
			$data->vth = 0;
			$co3 = 2 * $p;
			$t_convert = 0;

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if ($p > $half){
			$data->vth = (2 * $p) - $t;
			$co3 = 2 * ($t - $p);
			$t_convert = 0;

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if($p == $data->vth){
			$data->vth = $t;
			$co3 = 0;
			$t_convert = 0;

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		}

        if(!is_null($mdl) && $rumus < $mdl){
            $rumus = '<' . $mdl;
        }

        $rumus = str_replace(',', '', $rumus);

		$processed = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $processed;
    }

    // public function index($data, $id_parameter, $mdl){
    //     $a = $data->volume_titran_pH_4_5;
    //     $b = $data->volume_titran_pH_8_3;
    //     $N = $data->konsentrasi_titran;
    //     $V = $data->vs;
    //     // (((M-P)xNx50000)/V)x(61/50)x Fp
    //     $rumus = str_replace(',', '', (number_format(((($a - $b) * $N * 50000) / $V) * (61/50) * $data->fp, 4)));
        
    //     if(!is_null($mdl) && $rumus < $mdl){
    //         $rumus = '<' . $mdl;
    //     }

    //     $processed = [
    //         'hasil' => $rumus,
    //         'hasil_2' => '',
    //         'rpd' => '',
    //         'recovery' => '',
    //     ];
    //     return $processed;
    // }
}