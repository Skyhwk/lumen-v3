<?php

namespace App\HelpersFormula;
use Carbon\Carbon;
class SwabTest
{
    public function index($data, $id_parameter, $mdl){
        try {
			$n = floatval($data->jumlah_mikroba);
			$f = floatval($data->jumlah_pengencer);
			$d = floatval($data->volume);
			$a = floatval($data->luas);
			// Lakukan perhitungan terlebih dahulu
			$rumus = (($n * $f) / $a) * $d;

            $hasil2 = (($n * $f) / ($a / 100)) * $d;

            $hasil3 = (($n * $f) / ($a / 25)) * $d;

            $hasil4 = (($n * $f) / ($a / 10000)) * $d;

			// Cek apakah id_param sesuai dan rumus kurang dari 1
			// if ($id_parameter == 227 || $id_parameter == 337) {
				// if ($rumus < 1) {
				// 	$rumus = '<1';
				// } else {
					// // Format hasil perhitungan ke 2 desimal
					// $rumus = number_format($rumus, 2, '.', '');
				// }
			// }
			$rumus = number_format($rumus, 2, '.', '');
            $hasil2 = number_format($hasil2, 2, '.', '');
            $hasil3 = number_format($hasil3, 2, '.', '');
            $hasil4 = number_format($hasil4, 2, '.', '');

            $satuan = 'CFU/cm2';

			$data = [
				// 'luas' => $data->luas,
				// 'jumlah_mikroba' => $data->jumlah_mikroba,
				// 'cairan_pengencer' => $data->jumlah_pengencer,
				// 'volume' => $data->volume,
                'satuan' => $satuan,
				'hasil' => $rumus,
                'hasil2' => $hasil2,
                'hasil3' => $hasil3,
                'hasil4' => $hasil4
			];

			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
			], 401);
		}
    }
}
