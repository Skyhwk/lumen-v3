<?php

namespace App\HelpersFormula;
use Carbon\Carbon;
use App\Services\LookUpRdm;
class EmisiHF
{
    public function index($data, $id_parameter, $mdl){
		$Vs = null;
		$Vstd = null;
		$vl = null;
		$st = null;
		$ks = null;
		$kb = null;
		$w1 = null;
		$w2 = null;
		$C = null;
		$C1 = null;
		$C2 = null;


		
		if (is_array($data->ks)) {
			$ks = array_sum($data->ks) / count($data->ks);
		}else {
			$ks = floatval($data->ks);
		}
		if (is_array($data->kb)) {
			$kb = array_sum($data->kb) / count($data->kb);
		}else {
			$kb = floatval($data->kb);
		}

        $tekanan_dry = LookUpRdm::getRdm();
        $Vs = \str_replace(",", "", number_format($data->volume_dry * (298 / (273 + $data->suhu)) * (($data->tekanan + $data->tekanan_dry - $data->nil_pv) / 760), 4));

        $C1 = \str_replace(",", "", number_format(((20 / 19) * (floatval($ks) - floatval($kb)) * (250 / 20)) / floatval($Vs), 4));
        $C2 = \str_replace(",", "", number_format(24.45 * (floatval($C1) / 20.01), 5));
        if (floatval($C1) < 0.0003)
            $C1 = '<0.0003';
        if (floatval($C2) < 0.00036)
            $C2 = '<0.00036';

        $data = [
            'tanggal_terima' => $data->tanggal_terima,
            'suhu' => $data->suhu,
            'Va' => $data->volume_dry,
            'Vs' => $Vs,
            'Vstd' => $Vstd,
            'Pa' => $data->tekanan,
            'Pm' => $data->tekanan_dry,
            'Pv' => $data->nil_pv,
            't' => $data->durasi_dry,
            'durasi' => $data->durasi_dry,
            'flow' => $data->flow,
            'vl' => $vl,
            'st' => $st,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'w1' => $w1,
            'w2' => $w2,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
        return $data;
    }
}