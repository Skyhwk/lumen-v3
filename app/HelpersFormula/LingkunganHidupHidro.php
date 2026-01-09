<?php

namespace App\HelpersFormula;

use Carbon\Carbon;
class LingkunganHidupHidro
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

        $Vs = \str_replace(",", "",number_format(($data->durasi * $data->average_flow) * (298/(273 + $data->suhu)) * ($data->tekanan/760), 4));
        if(floatval($Vs) <= 0) {
                $C = 0;
                $Qs = 0;
                $C1 = 0;
            }else {
                if ($id_parameter == 261) {
                    $C = \str_replace(",", "", number_format((((20/19)*($ks - $kb)* 12.5)/$Vs) * 1000, 4));
                    $C1 = \str_replace(",", "", number_format(((20/19)*($ks - $kb)* 12.5)/$Vs, 4));
                    $C2 = \str_replace(",", "", number_format(24.45*($C1/20.01), 4));
                    $satuan = 'ug/Nm3';
                } else if ($id_parameter == 256 || $id_parameter == 568) { // HCL
                    // C (mg/Nm3) = (((A-B)*fp*(36.5/35.5))/Vs)*1000
                    $C1 = \str_replace(",", "", number_format((((20/19)*($ks - $kb)* 12.5)/$Vs) * 1000, 4));

                    // C(ug/Nm3) = C(mg/Nm3) / 1000
                    $C = \str_replace(",", "", number_format($C1 / 1000, 4));

                    $satuan = 'mg/Nm3';
                }
            }

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
