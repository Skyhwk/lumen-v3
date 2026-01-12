<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class EmisiH2S
{
    public function index($data, $id_parameter, $mdl)
    {
        $Vs = null;
        $Vstd = null;
        $vl = null;
        $st = null;
        $ks = null;
        $kb = null;
        $w1 = null;
        $w2 = null;
        $satuan = null;

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



        if (is_array($data->ks)) {
            $ks = array_sum($data->ks) / count($data->ks);
        } else {
            $ks = floatval($data->ks);
        }
        if (is_array($data->kb)) {
            $kb = array_sum($data->kb) / count($data->kb);
        } else {
            $kb = floatval($data->kb);
        }

        $Vs = \str_replace(",", "", number_format($data->volume_dry * (298 / (273 + $data->suhu)) * (($data->tekanan + $data->tekanan_dry - $data->nil_pv) / 760), 4));

        // C(PPM) = (A x (B/C)) / D
        $c_ppm = number_format(floatval($ks) * (floatval($data->vtp) / floatval($data->vs)) / floatval($Vs), 4, '.', '');

        // HP = C(PPM) x (34 / 24.45)
        $C1 = floatval($c_ppm) * (34 / 24.45);

        // dd($C1, $c_ppm);
        // (ug/Nm3) = C2 x 1000
        $C = number_format(floatval($C1) * 1000, 4);

        $C2 = $c_ppm; // ppm

        $C3 = $C;

        $C1 = number_format($C1, 4);

        $C4 = $C1;


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
            'C3' => $C3,
            'C4' => $C4,
            'C5' => $C5,
            'C6' => $C6,
            'C7' => $C7,
            'C8' => $C8,
            'C9' => $C9,
            'C10' => $C10,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
        return $data;
    }
}
