<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class GetaranPersonal
{
	public function index($data, $id_parameter, $mdl)
	{
		$pengukuran_obj = json_decode($data->pengukuran);
		$totData = count((array)$pengukuran_obj);
		$bobot_frekuensi = (object) json_decode($data->bobot_frekuensi); // KE, KD, KF
		$durasi_pengukuran = $data->durasi_pengukuran;

		// Initialize all possible keys with zero
		$axes = ['x', 'y', 'z'];
		$numbers = range(1, 4);
		$data_values = [];

		// Create all possible keys (x1, x2, ..., z4)
		foreach ($axes as $axis) {
			foreach ($numbers as $num) {
				$data_values[$axis.$num] = 0;
			}
		}

		// Add percepatan keys
		foreach ($numbers as $num) {
			$data_values['percepatan'.$num] = 0;
		}

		// Count occurrences for each key
		$key_counts = [];

		// Process data
		foreach ($pengukuran_obj as $entry) {
			foreach ($entry as $key => $value) {
				if (array_key_exists($key, $data_values)) {
					$data_values[$key] += floatval($value);
					$key_counts[$key] = ($key_counts[$key] ?? 0) + 1;
				}
			}
		}
		
		// Calculate averages for each key
		$averages = [];
		foreach ($data_values as $key => $total) {
			$count = $key_counts[$key] ?? 0;
			$averages[$key] = $count > 0 ? $total / $count : 0;
		}

		// Group by axis
		$axis_groups = [];
		foreach ($averages as $key => $value) {
			$prefix = preg_replace('/[0-9]+/', '', $key);
			if (!isset($axis_groups[$prefix])) {
				$axis_groups[$prefix] = [];
			}
			$value > 0 && $axis_groups[$prefix][] = $value; // pastikan nilai bukan 0
		}

		// Calculate final averages
		$xx = isset($axis_groups['x']) ? array_sum($axis_groups['x']) / count($axis_groups['x']) : 0;
		$yy = isset($axis_groups['y']) ? array_sum($axis_groups['y']) / count($axis_groups['y']) : 0;
		$zz = isset($axis_groups['z']) ? array_sum($axis_groups['z']) / count($axis_groups['z']) : 0;
		$percepatan = isset($axis_groups['percepatan']) ? array_sum($axis_groups['percepatan']) / count($axis_groups['percepatan']) : 0;
		// Round to 4 decimal places
		$xx = round($xx, 4);
		$yy = round($yy, 4);
		$zz = round($zz, 4);
		$percepatan = round($percepatan, 4);


		// if ($data->satKecX == "mm/s2") {
		//     $xx = number_format($xx / 1000, 4);
		// } else if ($data->satKecX == "m/s2") {
		//     $xx = number_format($xx * 1000, 4);
		// }

		// if ($data->satKecY == "mm/s2") {
		//     $yy = number_format($yy / 1000, 4);
		// } else if ($data->satKecY == "m/s2") {
		//     $yy = number_format($yy * 1000, 4);
		// }

		// if ($data->satKecZ == "mm/s2") {
		//     $zz = number_format($zz / 1000, 4);
		// } else if ($data->satKecZ == "m/s2") {
		//     $zz = number_format($zz * 1000, 4);
		// }
		if ($data->satKecX == "mm/s2") {
			$xx = number_format($xx / 1000, 4);
		} else if ($data->satKecX == "m/s2") {
			$xx = number_format($xx, 4);
		}

		if ($data->satKecY == "mm/s2") {
			$yy = number_format($yy / 1000, 4);
		} else if ($data->satKecY == "m/s2") {
			$yy = number_format($yy, 4);
		}

		if ($data->satKecZ == "mm/s2") {
			$zz = number_format($zz / 1000, 4);
		} else if ($data->satKecZ == "m/s2") {
			$zz = number_format($zz, 4);
		}

		$hasil = json_encode([
			"X" => $xx, 
			"Y" => $yy, 
			"Z" => $zz
		]);
		$processed = [
			'hasil' => $hasil,
		];
		return $processed;
	}
}