<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupPM
{
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        // dd(count($data->ks));
        $kb = null;

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

        $Vstd = \str_replace(",", "",number_format($data->nilQs * $data->durasi, 4));
        // dd($Vstd,$nilQs,$dur);
        if((int)$Vstd <= 0) {
                $C = 0;
                $Qs = 0;
                $C1 = 0;
            }else {
                $C = \str_replace(",", "", number_format((($data->w2 - $data->w1) * 10 ** 6) / $Vstd, 4));
                $Qs = $data->nilQs;
                $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 6));
            }
        if ($id_parameter == 310 || $id_parameter == 311 || $id_parameter == 312) {
            // dd($C,$C1);
            if (floatval($C) < 0.56)
                $C = '<0.56';
            if (floatval($C1) < 0.00056)
                $C1 = '<0.00056';
        } else if ($id_parameter == 313 || $id_parameter == 314 || $id_parameter == 315) {
            if (floatval($C) < 0.58)
                $C = '<0.58';
            if (floatval($C1) < 0.00058)
                $C1 = '<0.00058';
        }
        // dd($data);
        $w1 = $data->w1;
        $w2 = $data->w2;

        $data = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => $data->average_flow,
            'durasi' => $data->durasi,
            // 'durasi' => $waktu,
            'tekanan_u' => $data->tekanan,
            'suhu' => $data->suhu,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'Qs' => $Qs,
            'w1' => $w1,
            'w2' => $w2,
            'b1' => $b1,
            'b2' => $b2,
            // 'hasil1' => $C,
            // 'hasil2' => $C1,
            // 'hasil3' => $C2,
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

        return $data;
    }

}