<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupLogamPb
{
    public function index($data, $id_parameter, $mdl)
    {
        $ks = null;
        if (is_array($data->ks)) {
            $ks = number_format(array_sum($data->ks) / count($data->ks), 4);
        } else {
            $ks = $data->ks;
        }
        $kb = null;
        if (is_array($data->kb)) {
            $kb = number_format(array_sum($data->kb) / count($data->kb), 4);
        } else {
            $kb = $data->kb;
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
        $satuan = '';

        $Vstd = $data->nilQs * $data->durasi;
        if($data->tipe_data == 'ulk') {
            $Vstd = $data->nilQs * $data->durasi;
        }else if($data->tipe_data == 'ambient') {
            $Vstd = $data->average_flow * $data->durasi;
        }
        if ((int) $Vstd <= 0) {
            $C = 0;
            $Qs = $data->nilQs;
            $C1 = 0;
        } else {
            if ($data->tipe_data == 'ulk') {                
                // C (mg/m3) = (((Ct - Cb)*Vt)/Vstd)
                $rawC16 = (($ks - $kb) * $data->vl) / $Vstd;
                
                // C16 = C17*1000
                $rawC15 = $rawC16 * 1000;
                
                // C (PPM) = (C17 / 207,2)*24,45
                $rawC2 = ($rawC16 / 207.2) * 24.45;
                
                $rawC14 = $rawC2;
                
                $C2 = \str_replace(",", "", number_format($rawC2, 5));
                $C14 = \str_replace(",", "", number_format($rawC14, 5));
                $C15 = \str_replace(",", "", number_format($rawC15, 5));
                $C16 = \str_replace(",", "", number_format($rawC16, 5));
                $satuan = 'mg/m3';
            }else if ($data->tipe_data == 'ambient') {
                $rawC = (($ks - $kb) * $data->vl * $data->st) / $Vstd;
                $rawC1 = $rawC / 1000;
                
                $C = \str_replace(",", "", number_format($rawC, 5));
                $C1 = \str_replace(",", "", number_format($rawC1, 5));
                $satuan = 'mg/m3';
            }
            $vl = $data->vl;
            $st = $data->st;
        }

        // dd($C, $C1, $C2);

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
