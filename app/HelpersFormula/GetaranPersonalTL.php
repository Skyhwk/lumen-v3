<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class GetaranPersonalTL
{
	public function index($data, $id_parameter, $mdl)
	{
		$pengukuran_obj = json_decode($data->pengukuran);
		$totData = count((array)$pengukuran_obj);
		$bobot_frekuensi = (object) json_decode($data->bobot_frekuensi); // KE, KD, KF
		$durasi_pengukuran = $data->durasi_pengukuran;

		// Looping Data untuk mendapatkan average untuk masing-masing X,Y,Z, dan percepatan masing-masing perulangan
		$data_pengukuran = [];
		foreach ($pengukuran_obj as $idx => $val) {
			$data_x = 0;
			$data_y = 0;
			$data_z = 0;
			$data_percepatan = 0;
			$total_index = 0;
			foreach($val as $idf => $vale) {
				$prefix = preg_replace('/[0-9]+/', '', $idf);
				if($prefix == "x"){
					$data_x += $vale;
					$total_index++; // Menghitung Jumlah Data Pengukuran pada x,y,z, dan percepatan
				}else if($prefix == "y"){
					$data_y += $vale;
				}else if($prefix == "z"){
					$data_z += $vale;
				}else if($prefix == "percepatan"){
					$data_percepatan += $vale;
				}
			}
			$avg_x = $data_x / $total_index;
			$avg_y = $data_y / $total_index;
			$avg_z = $data_z / $total_index;
			$avg_percepatan = $data_percepatan / $total_index;
			$data_pengukuran[] = [
				'x' => $avg_x,
				'y' => $avg_y,
				'z' => $avg_z,
				'percepatan' => $avg_percepatan,
				'durasi_paparan' => $val->durasi_paparan
			];
		}

		// A = Akar dari ((D)^2+(E)^2+(F)^2)
		// D = X, E = Y, F = Z
		// B = Waktu paparan getaran Data Pengukuran 1 - Pengukuran N
		$hp_sum = 0;
		foreach ($data_pengukuran as $idx => $val) {
			// Hitung A = Akar dari ((D)^2+(E)^2+(F)^2)
			$A = sqrt(
				(
					pow($val['x'], 2) +
					pow($val['y'], 2) +
					pow($val['z'], 2)
				)
			);

			// Hitung B/C
			$B_C = $val['durasi_paparan'] / floatval($durasi_pengukuran);

			// Hitung (A^2 * B/C) dan tambahkan ke hp_sum
			// ---------------- Baris ini dihold untuk parameter baru karena beda harga ---------------- //
            // $hp_sum += pow($A, 2) * $B_C;
            // ------------------- Diganti Sementara dengan Baris dibawah ------------------- //
			$hp_sum += $A;
		}

		// Hitung HP
		// $hp = number_format(sqrt($hp_sum),5, ".", "");
		$hp = number_format($hp_sum / count($data_pengukuran),5, ".", "");

		$processed = [
			'hasil' => $hp,
		];
		return $processed;
	}
}
