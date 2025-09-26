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

			// Cek apakah id_param sesuai dan rumus kurang dari 1
			if ($id_parameter == 227 || $id_parameter == 337) {
				if ($rumus < 1) {
					$rumus = '<1';
				} else {
					// Format hasil perhitungan ke 2 desimal
					$rumus = number_format($rumus, 2);
				}
			}

			$data = [
				'id_swab_header' => $data->id_header,
				'tanggal_terima' => $data->tgl_terima,
				'luas' => $data->luas,
				'jumlah_mikroba' => $data->jumlah_mikroba,
				'cairan_pengencer' => $data->jumlah_pengencer,
				'volume' => $data->volume,
				'hasil' => $rumus,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];
			
			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
			], 401);
		}
    }
}