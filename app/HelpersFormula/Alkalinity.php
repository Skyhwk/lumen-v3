<?php
namespace App\HelpersFormula;
use Carbon\Carbon;


class Alkalinity
{
    public function index($data, $id_parameter, $mdl){
        $rumus = round((($data->vts * $data->b * 1000) / 25 ) * $data->fp, 4);

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
