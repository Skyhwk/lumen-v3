<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganKerjaHF
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

        // Vs (L) = Va*(298/Ta))*(Pa)/760
        $Vs = number_format(($data->average_flow * $data->durasi) * (298 / $Ta) * (floatval($data->tekanan) / 760),5, '.', '');
        if(floatval($Vs) > 0) {
            // C (mg/Nm3) = ((((20/19)*(A-B)*FP)/Vs)
            $C1 = number_format(((((20 / 19) * ($ks - $kb) * $data->fp) / $Vs)),5, '.', '');
        }else{
            $C1 = 0;
        }

        // C1 = C2*1000
        $C = number_format($C1 * 1000,5, '.', '');

        // C (PPM) = ((((20/19)*(A-B)*FP)/Vs)*24,45)/20,01
        $C2 = number_format(((((20 / 19) * ($ks - $kb) * $data->fp) / $Vs) * 24.45) / 20.01,5, '.', '');

        $C14 = $C2;

        // Vs (L) = Laju alir*durasi pengambilan
        $Vs_alt = number_format($data->average_flow * $data->durasi, 5, '.', '');
        if(floatval($Vs_alt) < 0) {
            $C16 = 0;
        }else{
            // C (mg/m3) = ((((20/19)*(A-B)*FP)/Vs)
            $C16 = number_format(((((20 / 19) * ($ks - $kb) * $data->fp) / $Vs_alt)),5, '.', '');
        }

        // C16 = C17*1000
        $C15 = number_format($C16 * 1000,5, '.', '');

        $satuan = 'mg/Nm3';

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
