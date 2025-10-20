<?php
namespace App\HelpersFormula;
use Carbon\Carbon;


class Alkalinity
{
    public function index($data, $id_parameter, $mdl){
        $rumus = round((($data->vts * $data->kt * 50000) / $data->vs ) * $data->fp, 4);

        if(!is_null($mdl) && $rumus < $mdl){
            $rumus = '<' . $mdl;
        }

		$rumus = str_replace(",", "", $rumus);

		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
    }
}
