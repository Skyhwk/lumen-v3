<?php

namespace App\HelpersFormula;
use Carbon\Carbon;
use App\Services\LookUpRdm;

class EmisiNH3
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

        // $tekanan_dry = LookUpRdm::getRdm();
        $Vs = \str_replace(",", "", number_format($data->volume_dry * (298 / (273 + $data->suhu)) * (($data->tekanan + $data->tekanan_dry - $data->nil_pv) / 760), 4));

        // C2 (mg/Nm3) = (((A-B) x FP)/Vs) x (17/24.45)
        $C1 = \str_replace(",", "", number_format((((floatval($ks) - floatval($kb)) * $data->fp) / floatval($Vs)) * (17 / 24.45), 4));
        // (ug/Nm3) = C2 x 1000
        $C = \str_replace(",", "", number_format(floatval($C1) * 1000, 4));
        // C3 (PPM) = (((A-B) x FP)/Vs)
        $C2 = \str_replace(",", "", number_format((((floatval($ks) - floatval($kb)) * $data->fp) / floatval($Vs)), 4));
        $C3 = $C;
        $C4 = $C1;

        $satuan = "ppm";
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
