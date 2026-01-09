<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupH2S
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

        $hasil1_array = $hasil2_array = $hasil3_array = $hasil14_array = $hasil15_array = $hasil16_array = [];

        foreach ($data->ks as $key => $value) {
            $Ta = floatval($data->suhu_array[$key]) + 273;
            $Vu = round($data->average_flow * $data->durasi * (floatval($data->tekanan_array[$key]) / $Ta) * (298 / 760), 4);
            if(floatval($Vu) != 0.0) {
                // H2S (mg/m3) = (((A1 - A2)/Vs)*(34/24,45))/1000
                $C1_val = round((($value - $data->kb[$key]) / $Vu) * (34 / 24.45), 4);
            }else {
                $C1_val = 0;
            }

            // C1 = C2*1000
            $C_val = round($C1_val * 1000, 4);
            // if (in_array($data->parameter, ['H2S (8 Jam)','H2S (3 Jam)','H2S (24 Jam)'])) {
            //     // C (ug/Nm3) = ((A1-A2)/Vs)*(34/24.45)
            //     $C_val = round((($value - $data->kb[$key]) / $Vu) * (34 / 24.45), 4);
            // }

            // H2S (PPM) = (A1-A2)/Vs
            $C2_val = round(($value - $data->kb[$key]) / $Vu, 4);

            $C14_val = $C2_val;

            // H2S (mg/Nm3) = (((A1 - A2)/Rerata laju alir*t)*(34/24,45))/1000
            $C16_val = round((($value - $data->kb[$key]) / ($data->average_flow * $data->durasi)) * (34 / 24.45), 4);

            $C15_val = round($C16_val * 1000, 4);

            $hasil1_array[$key] = $C_val;
            $hasil2_array[$key] = $C1_val;
            $hasil3_array[$key] = $C2_val;
            $hasil14_array[$key] = $C14_val;
            $hasil15_array[$key] = $C15_val;
            $hasil16_array[$key] = $C16_val;
        }

        $C = round(array_sum($hasil1_array) / count($hasil1_array), 4);
        $C1 = round(array_sum($hasil2_array) / count($hasil2_array), 4);
        $C2 = round(array_sum($hasil3_array) / count($hasil3_array), 4);
        $C14 = round(array_sum($hasil14_array) / count($hasil14_array), 4);
        $C15 = round(array_sum($hasil15_array) / count($hasil15_array), 4);
        $C16 = round(array_sum($hasil16_array) / count($hasil16_array), 4);

        $satuan = 'mg/Nm3';

        if(count($hasil1_array) == 4){
            $satuan = 'ug/Nm3';
            $data_pershift = [
                'Shift 1' => $hasil1_array[0],
                'Shift 2' => $hasil1_array[1],
                'Shift 3' => $hasil1_array[2],
                'Shift 4' => $hasil1_array[3],
            ];
        }else if(count($hasil1_array) > 1){
            $data_pershift = [
                'Shift 1' => $hasil1_array[0],
                'Shift 2' => $hasil1_array[1] ?? null,
                'Shift 3' => $hasil1_array[2] ?? null,
            ];
        }

        $processed = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => $data->average_flow,
            'durasi' => $data->durasi,
            // 'durasi' => $waktu,
            'tekanan_u' => $data->tekanan,
            'suhu' => $data->suhu,
            'k_sample' => round($ks, 4) > 0 ? round($ks, 4) : 0,
            'k_blanko' => round($kb, 4) > 0 ? round($kb, 4) : 0,
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
            'data_pershift' => count($hasil1_array) > 1 ? $data_pershift : null,
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
