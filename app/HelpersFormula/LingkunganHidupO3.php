<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupO3
{
    public function index($data, $id_parameter, $mdl)
    {
        // dd($data);
        $ks = null;
        // dd(count($data->ks));
        $ks = $data->ks[0];
        $kb = $data->kb[0];

        $Ta = floatval($data->suhu) + 273;
        $Qs = null;
        $C = null;
        $C1 = null;
        $C2 = null;
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
        $satuan = null;

        $Vu = \str_replace(",", "",number_format($data->average_flow * $data->durasi * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
        if($Vu != 0.0) {
            $C = \str_replace(",", "", number_format(($ks / floatval($Vu)) * 1000, 4));
        }else {
            $C = 0;
        }
        $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
        $C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 48, 5));
        if (floatval($C) < 0.1419)
            $C = '<0.1419';
        if (floatval($C1) < 0.00014)
            $C1 = '<0.00014';
        if (floatval($C2) < 0.00007)
            $C2 = '<0.00007';
        $data = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => $data->average_flow,
            'durasi' => $data->durasi,
            'tekanan_u' => $data->tekanan,
            'suhu' => $data->suhu,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'Qs' => $Qs,
            'w1' => $w1,
            'w2' => $w2,
            'b1' => $b1,
            'b2' => $b2,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
            'satuan' => $satuan,
            'vl' => $vl,
            'st' => $st,
            'Vstd' => $Vstd,
            'V' => $V,
            'Vu' => $Vu,
            'Vs' => $Vs,
            'Ta' => $Ta,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
        // dd($data);
        return $data;
    }

}