<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class EmisiCl2
{

	public function index($data, $id_parameter, $mdl)
	{
		try {
			$isDivisionZero = false;
			// Vs Formula
			// Nilai DGM x (298/(273+Suhu udara)) x ((Tekanan Udara + Tekanan Meteran - Tekanan uap air Jenuh)/760
			if ((273 + $data->suhu) != 0 && 760 != 0) {
				$vs = ($data->nilaiDgm * (298 / (273 + $data->suhu)) * (($data->tekanan_udara + $data->tekanan_meteran - $data->tekanan_air) / 760));
			} else {
				$isDivisionZero = true;
				$vs = 0; // Handle division by zero
			}

			// C Formula
			// (((A-B) x 25 x 50 / V) / Vs) x 1000
			if ($data->volume_sample != 0 && $vs != 0) {
				$c = (((($data->konsentrasi_klorin - $data->konsentrasi_blanko) * 25 * 50) / $data->volume_sample) / $vs) * 1000;
			} else {
				$isDivisionZero = true;
				$c = 0; // Handle division by zero
			}

			// C1 Formula
			// ((0.316 x (A-B) x 25 x 50 / V) / Vs) x 1000
			if ($data->volume_sample != 0 && $vs != 0) {
				$c1 = ((0.316 * (($data->konsentrasi_klorin - $data->konsentrasi_blanko) * 25 * 50) / $data->volume_sample) / $vs) * 1000;
			} else {
				$isDivisionZero = true;
				$c1 = 0; // Handle division by zero
			}

			if ($isDivisionZero) {
				return 'gagal';
			}

			// C2 Formula
			// c1 x 3.16 
			$c2 = $c1 * 3.16;

			// Format 4 angka dibelakang koma
			$vs = str_replace(",", "", number_format($vs, 4));
			$c = str_replace(",", "", number_format($c, 4));
			$c1 = str_replace(",", "", number_format($c1, 4));
			$c2 = str_replace(",", "", number_format($c2, 4));

			$data = [
				'id_parameter' => $id_parameter,
				'tanggal_terima' => $data->tanggal_terima,
				'suhu' => $data->suhu,
				'Va' => null,
				'Vs' => $vs,
				'Vstd' => null,
				'Pa' => $data->tekanan_udara,
				'Pm' => $data->tekanan_meteran,
				'tekanan_air' => $data->tekanan_air,
				'Pv' => null,
				't' => null,
				'vl' => $data->volume_sample,
				'st' => null,
				'k_sample' => $data->konsentrasi_klorin,
				'k_blanko' => $data->konsentrasi_blanko,
				'w1' => null,
				'w2' => null,
				'C' => $c2,
				'C1' => $c,
				'C2' => $c1,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];

			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			], 401);
		}
	}
}