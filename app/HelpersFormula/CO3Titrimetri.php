<?php

namespace App\HelpersFormula;

class CO3Titrimetri
{
    public function index($data, $id_parameter, $mdl){
        $t = $data->vtm;
        $p = $data->vtp;
        $kt = $data->kt;
        $vs = $data->vs;
        $ph = $data->ph;

        $half = $t * 50 / 100;

		if($p == 0){
			$t_convert = 0;

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if ($p < $half){
			$t_convert = ((2 * $p) - ( 2 * pow(10, ($ph - 14))));

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if ($p == $half){
			$t_convert = ((2 * $p) - ( 2 * pow(10, ($ph - 14))));

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if ($p > $half){
			$t_convert = 2 * ($t - $p);

			$rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
		} else if($p == $t){
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

    // public function index($data, $id_parameter, $mdl) {
    //     $rumus = str_replace(",", "", number_format(((2 * ($data->p - $data->m)) * $data->n * 50000 / $data->v)/(30/50) * $data->fp, 4));

    //     if(!is_null($mdl) && $rumus< $mdl){
    //         $rumus= '<' . $mdl;
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