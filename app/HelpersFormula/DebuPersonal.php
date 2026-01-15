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
			$C = null;
			$C1 = null;
            $C2 = null;
            $C3 = null;
            $C4 = null;
            $C5 = null;
            $C6 = null;
            $C7 = null;
            $C8 = null;
            $C9 = null;
            $C10 = null;
            $C11 = null;
            $C12 = null;
            $C13 = null;
            $C14 = null;
            $C15 = null;
            $C16 = null;
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
				$C16 = ((($w2 - $w1) - ($b2 - $b1)) * 1000) / $vl; // C (mg/m3)
				if ($id_parameter == 222) { // Debu (P8J)
					$C16 = (($w2 - $w1) - ($b2 - $b1)) * (10 ** 3) / $vl; // C (mg/m3)
				}
				$C15 = number_format($C16 * 1000, 4, '.', ''); // C (ug/m3)
	
				$C16 = number_format($C16, 4, '.', ''); // C (mg/m3)
			} else {
				// Jika rerata waktu 0 maka tidak dibagi
				// Kasus ini terjadi apabila jam mulai dan jam pengambilan sama yang menyebabkan rerata waktu 0
				$C16 = ((($w2 - $w1) - ($b2 - $b1)) * 1000); // C (mg/m3)
				if ($id_parameter == 222) { // Debu (P8J)
					$C16 = (($w2 - $w1) - ($b2 - $b1)) * (10 ** 3); // C (mg/m3)
					$C15 = number_format($C16 * 1000, 4, '.', ''); // C (ug/m3)
		
					$C16 = number_format($C16, 4, '.', ''); // C (mg/m3)
				}else{
					$C15 = number_format($C16 * 1000, 4, '.', ''); // C (ug/m3)
		
					$C16 = number_format($C16, 4, '.', ''); // C (mg/m3)
				}
			}
			$vl_formatted = number_format($vl, 1);

			$satuan = 'mg/m3';
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
				'C' => isset($C) ? $C : null,
                'C1' => isset($C1) ? $C1 : null,
                'C2' => isset($C2) ? $C2 : null,
                'C3' => isset($C3) ? $C3 : null,
                'C4' => isset($C4) ? $C4 : null,
                'C5' => isset($C5) ? $C5 : null,
                'C6' => isset($C6) ? $C6 : null,
                'C7' => isset($C7) ? $C7 : null,
                'C8' => isset($C8) ? $C8 : null,
                'C9' => isset($C9) ? $C9 : null,
                'C10' => isset($C10) ? $C10 : null,
                'C11' => isset($C11) ? $C11 : null,
                'C12' => isset($C12) ? $C12 : null,
                'C13' => isset($C13) ? $C13 : null,
                'C14' => isset($C14) ? $C14 : null,
                'C15' => isset($C15) ? $C15 : null,
                'C16' => isset($C16) ? $C16 : null,
                'satuan' => $satuan,
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
