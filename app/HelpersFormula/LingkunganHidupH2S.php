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

        $hasil1_array = $hasil2_array = $hasil3_array = [];

        $Vu = round($data->average_flow * $data->durasi * (floatval($data->tekanan) / $Ta) * (298 / 760), 4);
        foreach ($data->ks as $key => $value) {
            if(floatval($Vu) != 0.0) {
                // H2S (mg/m3) = (((A1 - A2)/Vs)*(34/24,45))/1000
                $C1_val = round((($value - $data->kb[$key]) / $Vu) * (34 / 24.45) / 1000, 4);

                // C1 = C2*1000
                $C_val = round($C1_val * 1000, 4);

                // H2S (PPM) = (A1-A2)/Vs
                $C2_val = round(($value - $data->kb[$key]) / $Vu, 4);
            }else {
                $C_val = 0;
                $C1_val = 0;
                $C2_val = 0;
            }

            $hasil1_array[$key] = $C_val;
            $hasil2_array[$key] = $C1_val;
            $hasil3_array[$key] = $C2_val;
        }

        $C = array_sum($hasil1_array) / count($hasil1_array);
        $C1 = array_sum($hasil2_array) / count($hasil2_array);
        $C2 = array_sum($hasil3_array) / count($hasil3_array);


        if (floatval($C) < 1.39)
            $C = '<1.39';
        if (floatval($C1) < 0.0022)
            $C1 = '<0.0022';
        if (floatval($C2) < 0.0010)
            $C2 = '<0.0010';

        $satuan = 'mg/m3';

        $data_pershift = [
            'Shift 1' => $hasil1_array[0],
            'Shift 2' => $hasil1_array[1] ?? null,
            'Shift 3' => $hasil1_array[2] ?? null,
        ];

        if(count($hasil1_array) == 4){
            $data_pershift = [
                'Shift 1' => $hasil1_array[0],
                'Shift 2' => $hasil1_array[1],
                'Shift 3' => $hasil1_array[2],
                'Shift 4' => $hasil1_array[3],
            ];
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
