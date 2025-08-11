<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupCl2
{
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        // dd(count($data->ks));
        if (is_array($data->ks)) {
            $ks = number_format(array_sum($data->ks) / count($data->ks), 4);
        }else {
            $ks = $data->ks;
        }
        $kb = null;
        if (is_array($data->kb)) {
            $kb = number_format(array_sum($data->kb) / count($data->kb), 4);
        }else {
            $kb = $data->kb;
        }

        $Ta = floatval($suhu) + 273;
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

        $Vu = \str_replace(",", "",number_format(($data->average_flow) * $data->durasi * ($data->tekanan/$Ta) * (298/760), 4));
        if((int)$Vu <= 0) {
            $C = 0;
            $Qs = 0;
            $C1 = 0;
        }else {
            $C = \str_replace(",", "", number_format(($ks/$Vu) * 1000000, 3));
            $C1 = \str_replace(",", "", number_format(($ks/$Vu) * 0.001, 3));
            $C2 = \str_replace(",", "", number_format(($C1*0.001) * (24.45/71), 4));
        }
        if (floatval($C) < 4.000)
            $C = '<4.000';
        if (floatval($C1) < 0.004)
            $C1 = '<0.004';
        if (floatval($C2) < 0.0013)
            $C2 = '<0.0013';

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
            'satuan' => $satuan,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
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