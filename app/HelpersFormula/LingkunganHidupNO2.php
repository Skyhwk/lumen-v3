<?php

namespace App\HelpersFormula;

use Carbon\Carbon;
class LingkunganHidupNO2
{
    public function index($data, $id_parameter, $mdl) {
        if($data->use_absorbansi) {
            $ks = array_sum($data->ks[0]) / count($data->ks[0]);
            $kb = array_sum($data->kb[0]) / count($data->kb[0]);
        }else{
            $ks = array_sum($data->ks) / count($data->ks);
            $kb = array_sum($data->kb) / count($data->kb);
            // dd($data);
        }

        $Ta = floatval($data->suhu) + 273;
        $Qs = null;
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
        $C11 = null;
        $C12 = null;
        $C13 = null;
        $C14 = null;
        $C15 = null;
        $C16 = null;
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
        // dd($Vu);
        if($Vu != 0.0) {
            // C (ug/Nm3) = (a/Vu)*(10/25)*1000
            $C = \str_replace(",", "", number_format(($ks / floatval($Vu)) * (10 / 25) * 1000, 4));

        }else {
            $C = 0;
            $C1 = 0;
            $C2 = 0;
        }

        // C (mg/m3) = C (ug/Nm3)/1000
        $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));

        // C (PPM) = 24.45*(C(mg/m3)/46)
        $C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 46, 5));

        $C14 = $C2;

        $Vu_alt = \str_replace(",", "",number_format($data->average_flow * $data->durasi, 4));

        if(floatval($Vu_alt) != 0.0) {
            // C (ug/Nm3) = (a/Vu)*(10/25)*1000
            $C15 = \str_replace(",", "", number_format(($ks / floatval($Vu_alt)) * (10 / 25) * 1000, 5));
        }else{
            $C15 = 0;
        }

        $C16 = \str_replace(",", "", number_format(floatval($C15) / 1000, 5));

        $satuan = 'ug/Nm3';

        $processed = [
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
            'C' => isset($C) ? $C : null,
            'C1' => isset($C1) ? $C1 : null,
            'C2' => isset($C2) ? $C2 : null,
            'C3' => isset($C3) ? $C3 : null,
            'C4' => isset($C4) ? $C4 : null,
            'C5' => isset($C5) ? $C5 : null,
            'C6' => isset($C6) ? $C6 : null,
            'C7' => isset($C7) ? $C7 : null,
            'C8' => isset($C8) ? $C8 : null,
            'C9' => isset($C9) ? $C9 : null,
            'C10' => isset($C10) ? $C10 : null,
            'C11' => isset($C11) ? $C11 : null,
            'C12' => isset($C12) ? $C12 : null,
            'C13' => isset($C13) ? $C13 : null,
            'C14' => isset($C14) ? $C14 : null,
            'C15' => isset($C15) ? $C15 : null,
            'C16' => isset($C16) ? $C16 : null,
            'satuan' => $satuan,
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

        return $processed;
    }

}
