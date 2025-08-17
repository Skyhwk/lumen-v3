<?php 
namespace App\HelpersFormula;
use Carbon\Carbon;


class AlkalinitasH
{
    public function index($data, $id_parameter, $mdl){
        $half = $data->vtm * 50 / 100;

		if($data->vtp == 0){
			$data->vth = 0;
			$co3 = 0;
			$caco3 = $data->vtm;

			$rumus = number_format(($data->vth * $data->kt * 50000) / $data->vs, 2);
			$caco = number_format(($caco3 * $data->kt * 50000) / $data->vs, 2);
			$RPD = '';
			$Recovery = '';

		} else if ($data->vtp < $half){
			$data->vth = 0;
			$co3 = 2 * $data->vtp;
			$caco3 = $data->vtm - (2 * $data->vtp);

			$rumus = number_format(($data->vth * $data->kt * 50000) / $data->vs, 2);
			$caco = number_format(($caco3 * $data->kt * 50000) / $data->vs, 2);
			$RPD = '';
			$Recovery = '';
		} else if ($data->vtp == $half){
			$data->vth = 0;
			$co3 = 2 * $data->vtp;
			$caco3 = 0;

			$rumus = number_format(($data->vth * $data->kt * 50000) / $data->vs, 2);
			$caco = number_format(($caco3 * $data->kt * 50000) / $data->vs, 2);
			$RPD = '';
			$Recovery = '';
		} else if ($data->vtp > $half){
			$data->vth = (2 * $data->vtp) - $data->vtm;
			$co3 = 2 * ($data->vtm - $data->vtp);
			$caco3 = 0;

			$rumus = number_format(($data->vth * $data->kt * 50000) / $data->vs, 2);
			$caco = number_format(($caco3 * $data->kt * 50000) / $data->vs, 2);
			$RPD = '';
			$Recovery = '';
		} else if($data->vtp == $data->vth){
			$data->vth = $data->vtm;
			$co3 = 0;
			$caco3 = 0;

			$rumus = number_format(($data->vth * $data->kt * 50000) / $data->vs, 2);
			$caco = number_format(($caco3 * $data->kt * 50000) / $data->vs, 2);
			$RPD = '';
			$Recovery = '';
		}

		$data = [
			'hasil' => $rumus,
			'hasil_2' => $caco,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
    }
}