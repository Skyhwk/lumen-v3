<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganKerjaHCL
{
    public function index($data, $id_parameter, $mdl)
    {
        if ($data->use_absorbansi) {
            $ks = array_sum($data->ks[0]) / count($data->ks[0]);
            $kb = array_sum($data->kb[0]) / count($data->kb[0]);
        } else {
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

        $C_value = $C1_value = $C2_value = $C14_value = $C15_value = $C16_value = [];

        $Vs = round($data->durasi * $data->average_flow * (298 / (273 + $data->suhu)) * ($data->tekanan / 760), 4);
        foreach ($data->ks as $key => $value) {
            if (floatval($Vs) <= 0) {
                $C1 = 0;
                $Qs = 0;
            } else {
                // C (mg/Nm3) = (((A-B)*fp*(36.5/35.5))/Vs)*1000
                $C1 = round((($value - $data->kb[$key]) * $data->fp * (36.5 / 35.5) / $Vs) * 1000, 4);
            }

            // C(ug/Nm3) = C(mg/Nm3) / 1000
            $C = round($C1 / 1000, 4);

            // (C2 / 24.45)*36,46)
            // revisi menjadi
            // (C2 / 36,46)*24.45)
            $C2 = round(($C1 / 36.46) * 24.45, 4);

            $C14 = $C2;
            // Vs = (Durasi Pengambilan Data)
            $Vs_C16 = round($data->durasi * $data->average_flow, 4);

            // C (mg/Nm3) = (((A-B)*fp*(36.5/35.5))/Vs)*1000
            if(floatval($Vs_C16) <= 0){
                $C16 = 0;
            }else{
                $C16 = round((($value - $data->kb[$key]) * $data->fp * (36.5 / 35.5) / $Vs_C16)* 1000, 4);
            }
            $C15 = round($C16 * 1000, 4);

            $C_value[] = $C;
            $C1_value[] = $C1;
            $C2_value[] = $C2;
            $C14_value[] = $C14;
            $C15_value[] = $C15;
            $C16_value[] = $C16;
        }

        if($data->parameter === 'HCl'){
            $C = round(array_sum($C_value) / count($C_value), 4);
            $C1 = round(array_sum($C1_value) / count($C1_value), 4);
            $C2 = round(array_sum($C2_value) / count($C2_value), 4);
            $C14 = round(array_sum($C14_value) / count($C14_value), 4);
            $C15 = round(array_sum($C15_value) / count($C15_value), 4);
            $C16 = round(array_sum($C16_value) / count($C16_value), 4);
        }else{
            $C1 = round(array_sum($C1_value) / count($C1_value), 4);

            $data_pershift = [
                'Shift 1' => $C1_value[0],
                'Shift 2' => $C1_value[1] ?? 0,
                'Shift 3' => $C1_value[2] ?? 0
            ];
        }

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
            'data_pershift' => count($C1_value) > 1 ? $data_pershift : null,
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
