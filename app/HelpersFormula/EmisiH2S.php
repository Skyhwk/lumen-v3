<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class EmisiH2S
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
        $satuan = null;

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

        // C(PPM) = (A x (B/C)) / D
        $c_ppm = number_format((floatval($ks) * (floatval($data->vtp) / floatval($data->vs))) / floatval($Vs), 4, '.', '');

        // HP = C(PPM) x (34 / 24.45)
        $C = number_format(floatval($c_ppm) * (34 / 24.45), 4);

        if(!is_null($mdl) && $C < $mdl){
            $C = '<'. $mdl;
        }

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
            'satuan' => $satuan,
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
