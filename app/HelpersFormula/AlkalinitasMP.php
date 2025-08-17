<?php
namespace App\HelpersFormula;
use Carbon\Carbon;


class AlkalinitasMP
{
    public function index($data, $id_parameter, $mdl){
        $rumus = number_format(($data->vts* $data->kt * 50000)/ $data->vs, 2);

		$data = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
    }
}