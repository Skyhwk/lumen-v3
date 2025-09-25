<?php

namespace App\HelpersFormula;

use App\Models\DebuPersonalHeader;
use Carbon\Carbon;

class DebuPersonal
{
	public function index($data, $id_parameter, $mdl)
	{
		$c = null;
		$w1 = null;
		$w2 = null;
		$b1 = null;
		$b2 = null;
		$vl = null;

		$average_flow = floatval($data->average_flow);
		$average_time = floatval($data->average_time);
		$w1 = floatval($data->w1);
		$w2 = floatval($data->w2);
		$b1 = floatval($data->b1);
		$b2 = floatval($data->b2);

		// Calculate volume
		$vl = $average_flow * $average_time;
		if ($vl > 0) {
			$c = (($w2 - $w1) - ($b2 - $b1)) * (10 ** 3) / $vl; // C (mg/m3)
		} else {
			// Jika rerata waktu 0 maka tidak dibagi
			// Kasus ini terjadi apabila jam mulai dan jam pengambilan sama yang menyebabkan rerata waktu 0
			$c = (($w2 - $w1) - ($b2 - $b1)) * (10 ** 3); // C (mg/m3)
		}

		$processed = [
			'hasil1' => number_format($c, 5),
			'satuan' => 'mg/m3'
		];

		return $processed;
	}
}