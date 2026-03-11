<?php

namespace App\HelpersFormula;
use Carbon\Carbon;
use App\Services\LookUpRdm;
class EmisiHCL
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

        $Vs = \str_replace(",", "", number_format($data->volume_dry * (298 / (273 + $data->suhu)) * (($data->tekanan + $data->tekanan_dry - $data->nil_pv) / 760), 4));
        // dd($data->volume_dry, $data->suhu, $data->tekanan, $data->tekanan_dry, $data->nil_pv);
        try {
            // $nilbag = \str_replace(",", "", );
            // C2 (mg/Nm3) = (((A-B) x FP x (36.5/35.5))/Vs) x 1000
            $C1 = (((floatval($ks) - floatval($kb)) * $data->fp * (36.5 / 35.5)) / floatval($Vs)) * 1000;
            // "C1 (ug/Nm3) = C2 x 1000"
            $C = floatval($C1) * 1000;
            // C3 (PPM) = 24.45 x (C(mg/m3)/36.5)
            $C2 = 24.45 * (floatval($C1) / 36.5);
            $C3 = $C;
            $C4 = $C1;
        }catch(\Throwable $e) {
            dd($e);
        }

        $C = number_format($C, 4,'.','');
        $C1 = number_format($C1, 4,'.','');
        $C2 = number_format($C2, 4,'.','');
        $C3 = number_format($C3, 4,'.','');
        $C4 = number_format($C4, 4,'.','');
        
        $satuan = 'mg/Nm3';
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
            'C3' => $C3,
            'C4' => $C4,
            'satuan' => $satuan,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
        return $data;
    }
}
