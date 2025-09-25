<?php

namespace App\HelpersFormula;

use App\Models\DebuPersonalHeader;
use Carbon\Carbon;

class DebuPersonal
{
	public function index($data, $id_parameter, $mdl)
	{
		try {

			$Ta = floatval($data->average_suhu) + 273;
			$Qs = null;
			$c = null;
			$c1 = null;
			$c2 = null;
			$w1 = null;
			$w2 = null;
			$b1 = null;
			$b2 = null;
			$Vstd = null;
			$V = null;
			$Vu = null;
			$Vs = null;
			$vl = null;
			$st = null;
			$ks = null;
			$kb = null;

			$average_flow = floatval($data->average_flow);
			$average_time = floatval($data->average_time);
			$w1 = floatval($data->w1);
			$w2 = floatval($data->w2);
			$b1 = floatval($data->b1);
			$b2 = floatval($data->b2);

			// Calculate volume
			$vl = $average_flow * $average_time;
			// dd($w1, $w2, $b1, $b2,$vl,$flow, $waktu);
			if ($vl > 0) {
				$c1 = ((($w2 - $w1) - ($b2 - $b1)) * 1000) / $vl; // C (mg/m3)
				if ($id_parameter == 222) { // Debu (P8J)
					$c1 = (($w2 - $w1) - ($b2 - $b1)) * (10 ** 3) / $vl; // C (mg/m3)
				}
			} else {
				// Jika rerata waktu 0 maka tidak dibagi
				// Kasus ini terjadi apabila jam mulai dan jam pengambilan sama yang menyebabkan rerata waktu 0
				$c1 = ((($w2 - $w1) - ($b2 - $b1)) * 1000); // C (mg/m3)
				if ($id_parameter == 222) { // Debu (P8J)
					$c1 = (($w2 - $w1) - ($b2 - $b1)) * (10 ** 3); // C (mg/m3)
				}
			}
			$vl_formatted = number_format($vl, 1);
			$c1_formatted = number_format($c1, 4);
			$c = number_format($c1 * 1000, 4); // C (ug/m3)

			// $satuan = 'mg/m3';
			$satuan = null;
			$processed = [
				'tanggal_terima' => $data->tanggal_terima,
				'flow' => $average_flow,
				'durasi' => $average_time,
				'tekanan_u' => $data->average_tekanan_udara,
				'suhu' => $data->average_suhu,
				'k_sample' => $ks,
				'k_blanko' => $kb,
				'Qs' => $Qs,
				'w1' => $w1,
				'w2' => $w2,
				'b1' => $b1,
				'b2' => $b2,
				'C' => $c,
				'C1' => $c1_formatted,
				'C2' => $c2,
				'vl' => $vl_formatted,
				'st' => $st,
				'Vstd' => $Vstd,
				'V' => $V,
				'Vu' => $Vu,
				'Vs' => $Vs,
				'Ta' => $Ta,
				'satuan' => $satuan,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];

			return $processed;
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage());
		}
	}
}