<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class GetaranGKBmmUA
{
	public function index($data, $id_parameter, $mdl)
	{
		$dataa = json_decode($data->nilai_pengukuran);
		$totData = count(array_keys(get_object_vars($dataa)));

		$min_per = 0;
		$max_per = 0;
		$min_kec = 0;
		$max_kec = 0;
		$perminT = 0;
		$permaxT = 0;
		$kecminT = 0;
		$kecmaxT = 0;
		$perminP = 0;
		$permaxP = 0;
		$kecminP = 0;
		$kecmaxP = 0;
		$perminB = 0;
		$permaxB = 0;
		$kecminB = 0;
		$kecmaxB = 0;

		foreach ($dataa as $idx => $val) {


			foreach ($val as $idf => $vale) {

				if ($idf == "min_per") {
					$min_per += $vale;
				} else if ($idf == "max_per") {
					$max_per += $vale;
				} else if ($idf == "min_kec") {
					$min_kec += $vale;
				} else if ($idf == "max_kec") {
					$max_kec += $vale;
				} else if ($idf == "perminT") {
					$perminT += $vale;
				} else if ($idf == "permaxT") {
					$permaxT += $vale;
				} else if ($idf == "kecminT") {
					$kecminT += $vale;
				} else if ($idf == "kecmaxT") {
					$kecmaxT += $vale;
				} else if ($idf == "perminP") {
					$perminP += $vale;
				} else if ($idf == "permaxP") {
					$permaxP += $vale;
				} else if ($idf == "kecminP") {
					$kecminP += $vale;
				} else if ($idf == "kecmaxP") {
					$kecmaxP += $vale;
				} else if ($idf == "perminB") {
					$perminB += $vale;
				} else if ($idf == "permaxB") {
					$permaxB += $vale;
				} else if ($idf == "kecminB") {
					$kecminB += $vale;
				} else if ($idf == "kecmaxB") {
					$kecmaxB += $vale;
				}

			}

		}


		$min_per_1 = number_format($min_per / $totData, 4);
		$max_per_1 = number_format($max_per / $totData, 4);
		$min_kec_1 = number_format($min_kec / $totData, 4);
		$max_kec_1 = number_format($max_kec / $totData, 4);
		$perminT_1 = number_format($perminT / $totData, 4);
		$permaxT_1 = number_format($permaxT / $totData, 4);
		$kecminT_1 = number_format($kecminT / $totData, 4);
		$kecmaxT_1 = number_format($kecmaxT / $totData, 4);
		$perminP_1 = number_format($perminP / $totData, 4);
		$permaxP_1 = number_format($permaxP / $totData, 4);
		$kecminP_1 = number_format($kecminP / $totData, 4);
		$kecmaxP_1 = number_format($kecmaxP / $totData, 4);
		$perminB_1 = number_format($perminB / $totData, 4);
		$permaxB_1 = number_format($permaxB / $totData, 4);
		$kecminB_1 = number_format($kecminB / $totData, 4);
		$kecmaxB_1 = number_format($kecmaxB / $totData, 4);

		$percep = number_format(($min_per_1 + $max_per_1) / 2, 4);
		$percepT = number_format(($perminT_1 + $permaxT_1) / 2, 4);
		$percepP = number_format(($perminP_1 + $permaxP_1) / 2, 4);
		$percepB = number_format(($perminB_1 + $permaxB_1) / 2, 4);
		$kercep = number_format(($min_kec_1 + $max_kec_1) / 2, 4);
		$kercepT = number_format(($kecminT_1 + $kecmaxT_1) / 2, 4);
		$kercepP = number_format(($kecminP_1 + $kecmaxP_1) / 2, 4);
		$kercepB = number_format(($kecminB_1 + $kecmaxB_1) / 2, 4);

		$kercep_mms = $kercep;
		$kercepT_mms = $kercepT;
		$kercepP_mms = $kercepP;
		$kercepB_mms = $kercepB;
		$percep_ms2 = $percep;
		$percepT_ms2 = $percepT;
		$percepP_ms2 = $percepP;
		$percepB_ms2 = $percepB;

		if ($data->sat_kec == "m/s") {
			$kercep_mms = round(($kercep * 10000), 4);
			$kercepT_mms = round(($kercepT * 10000), 4);
			$kercepP_mms = round(($kercepP * 10000), 4);
			$kercepB_mms = round(($kercepB * 10000), 4);
		}

		if ($data->sat_per == "mm/s2") {
			$percep_ms2 = round(($percep / 10000), 4);
			$percepT_ms2 = round(($percepT / 10000), 4);
			$percepP_ms2 = round(($percepP / 10000), 4);
			$percepB_ms2 = round(($percepB / 10000), 4);
		}
		$processed = [
			'kercep_mms' => $kercep_mms,
			'kercepT_mms' => $kercepT_mms,
			'kercepP_mms' => $kercepP_mms,
			'kercepB_mms' => $kercepB_mms,
			'percep_ms2' => $percep_ms2,
			'percepT_ms2' => $percepT_ms2,
			'percepP_ms2' => $percepP_ms2,
			'percepB_ms2' => $percepB_ms2,

		];
		return $processed;
	}
}