<?php

namespace App\HelpersFormula;

class FecalColiMPN {
    public function index($data, $id_parameter, $mdl) {
        $nilai = $data->hp * (int)$data->fp;
		$nilTambahan = NULL;

		if($id_parameter == 67) {
			$nilTambahan = [
				"Kombinasi Tabung Positif-1" => $data->tb1,
				"Kombinasi Tabung Positif-2" => $data->tb2,
				"Kombinasi Tabung Positif-3" => $data->tb3,
				"Jumlah Tabung Positif (10 mL)" => $data->nil10ml,
				"Jumlah Tabung Positif (1 mL)" => $data->nil1ml,
				"Jumlah Tabung Positif (0.1 mL)" => $data->nil01ml,
				"Jumlah Tabung Positif (0.01 mL)" => $data->nil001ml,
				"Jumlah Tabung Positif (0.001 mL)" => $data->nil0001ml
			];
			$nilTambahan = json_encode($nilTambahan);
		}
		$data = [
			'hasil' => $nilai,
			'nilai_tambahan_analyst' => $nilTambahan,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $data;
    }
}