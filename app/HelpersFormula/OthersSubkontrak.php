<?php

namespace App\HelpersFormula;


class OthersSubkontrak
{
	public function index($data, $id_parameter, $mdl)
	{
		// $rumus = $hp * $fp;
		$not_decimal = [13, 140, 163];
		$is_5_decimal = [3, 4, 22, 23, 24, 36, 37, 42, 43, 49, 50, 77, 78, 100, 101, 112, 113, 155, 156, 157, 53, 54, 190, 189, 4, 3, 167, 166];
		$is_4_decimal = [65, 66, 148, 149, 150, 15, 16, 17, 187, 105, 122, 121, 7, 6, 21, 20, 19, 171, 170, 147, 146, 116, 109, 104, 103];
		$is_3_decimal = [31, 33, 34, 40, 96, 97, 520, 537, 546, 547, 132];
		$is_2_decimal = [39, 102, 545, 130, 131];
		$is_0_decimal = [158, 174, 60, 82, 175];
		//belakang koma = 1 [141 ] Salinitas

		// $decimal = !is_null($mdl) ? $this->countInputDigits($mdl) : 4;

		// if(in_array($id_parameter, $is_5_decimal)){
		// 	$decimal = 5;
		// }else if(in_array($id_parameter, $is_4_decimal)){
		// 	$decimal = 4;
		// }else if(in_array($id_parameter, $is_3_decimal)){
		// 	$decimal = 3;
		// }else if(in_array($id_parameter, $is_2_decimal)){
		// 	$decimal = 2;
		// }else if(in_array($id_parameter, $is_0_decimal)){
		// 	$decimal = 0;
		// }

		// if ($id_parameter == 543 || $id_parameter == 179 || $id_parameter == 141) {
		// 	$decimal = 1;
		// }
		// dd($data->hp);
		if (strpos($data->hp, '<') !== false || strpos($data->hp, '>') !== false || strpos($data->parameter, 'Rasa') !== false || !is_numeric($data->hp) || !isset($data->fp)) {
			// dd('masuk');
			$rumus = str_replace(',','.',($data->hp));
		}else{
			// dd('masuk1');
			$rumus = str_replace(',','',$data->hp * $data->fp);

			// if (!is_null($mdl) && $rumus < $mdl) {
			// 	$rumus = '<' . $mdl;
			// }
		}

		$processed = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $processed;
	}

	function countInputDigits($angka) {
		// Pastikan angka dalam format string dengan titik desimal
		$angka = (string) $angka;

		if (strpos($angka, '.') !== false) {
			$pecah = explode('.', $angka);
			return strlen($pecah[1]);
		}else{
			$jumlah = strlen((string) $angka);
			if($jumlah == 1) return 0;
			return $jumlah;
		}

		return 4;
	}

}
