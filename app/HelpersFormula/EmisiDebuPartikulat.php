<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class EmisiDebuPartikulat
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
        $C = null;
        $C1 = null;
        $C2 = null;

        if (isset($data->ks)) {
            if (is_array($data->ks)) {
                $ks = array_sum($data->ks) / count($data->ks);
            } else {
                $ks = floatval($data->ks);
            }
        }

        if (isset($data->kb)) {
            if (is_array($data->kb)) {
                $kb = array_sum($data->kb) / count($data->kb);
            } else {
                $kb = floatval($data->kb);
            }
        }

        // Vstd (Nm3) = (Rerata Laju Alir TSP (Liter) x t) x (298/760) x (Pa/Ta) / 1000
        $Vstd = ($data->flow * $data->durasi_dry) * (298 / 760) * ($data->tekanan / (273 + $data->suhu)) / 1000;

        // $raCwC = ((floatval($data->w2) - floatval($data->w1)) * 10 ** 6) / floatval($Vstd);
        $C = ((floatval($data->w2) - floatval($data->w1)) * 10 ** 6) / floatval($Vstd);
        // $C = \str_replace(",", "", number_format($rawC, 4));
        $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 4));
        $C3 = $C;
        $C4 = $C1;
        $w1 = $data->w1;
        $w2 = $data->w2;

        $C = number_format($C, 4, '.', '');
        $C1 = number_format($C1, 4, '.', '');
        $C3 = number_format($C3, 4, '.', '');
        $C4 = number_format($C4, 4, '.', '');
        $Vstd = number_format($Vstd, 4, '.', '');
        // dd($C, $C1);

        $satuan = 'ug/Nm3';
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
