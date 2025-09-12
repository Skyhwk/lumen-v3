<?php

namespace App\HelpersFormula;
use Carbon\Carbon;
class TotalColiUSEPA
{
	public function index($data, $id_parameter, $mdl)
	{
        // ((Jumlah Koloni coliform*100)/mL sampel filtrasi)*FP
		$nilai = number_format($data->jumlah_koloni * 100 / $data->sampel_filtrasi * $data->fp, 0, '.', '');

        if(!is_null($mdl) && $nilai < $mdl){
            $nilai = '<' . $mdl;
        }

		$data = [
			'hasil' => $nilai,
			'rpd' => '',
			'recovery' => ''
		];
		return $data;
	}
}
