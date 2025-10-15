<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupO3
{
    public function index($data, $id_parameter, $mdl) {
        if($data->use_absorbansi) {
            $ks = array_sum($data->ks[0]) / count($data->ks[0]);
            $kb = array_sum($data->kb[0]) / count($data->kb[0]);
        }else{
            $ks = array_sum($data->ks) / count($data->ks);
            $kb = array_sum($data->kb) / count($data->kb);
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

        $C_value = []; // ug/Nm3;
        $C1_value = [];
        $C2_value = [];
        // dd($data->average_flow);
        foreach($data->ks as $key_ks => $item_ks) {
            foreach ($data->average_flow as $key => $value) {
                $Vu = \str_replace(",", "",number_format($value * $data->durasi[$key] * (floatval($data->tekanan) / (floatval($data->suhu) + 273)) * (298 / 760), 4));
                // if($key == 0) dd('Vu : '.$Vu, 'flow :'. $value, 'durasi : '.$data->durasi[$key], 'tekanan : '. $data->tekanan, 'Suhu :'. $data->suhu, 'Avg Penjerapan : '. $item_ks[$key]);
                if($Vu != 0.0) {
                    $C = \str_replace(",", "", number_format(($item_ks[$key] / floatval($Vu)) * 1000, 4));
                }else {
                    $C = 0;
                }
                $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
                // dd($C1);
                $C2 = \str_replace(",", "", number_format((floatval($C1) / 48) * 24.45, 5));

                $C_value[$key_ks][$key] = $C;
                $C1_value[$key_ks][$key] = $C1;
                $C2_value[$key_ks][$key] = $C2;
            }
        }

        $C = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C_value);

        $C1 = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C1_value);

        $C2 = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C2_value);

        $avg_hasil_C = array_sum($C) / count($C);

        $avg_hasil_C1 = array_sum($C1) / count($C1);

        $avg_hasil_C2 = array_sum($C2) / count($C2);

        if (floatval($avg_hasil_C) < 0.1419)
            $avg_hasil_C = '<0.1419';
        if (floatval($avg_hasil_C1) < 0.00014)
            $avg_hasil_C1 = '<0.00014';
        if (floatval($avg_hasil_C2) < 0.00007)
            $avg_hasil_C2 = '<0.00007';

        // dd($avg_pershift);

        $satuan = 'ug/Nm3';
        if(!is_null($mdl) && $avg_hasil_C < $mdl){
            $avg_hasil_C = '<'. $mdl;
        }
        // dd($avg_hasil);

        $processed = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => array_sum($data->average_flow) / count($data->average_flow),
            'durasi' => array_sum($data->durasi) / count($data->durasi),
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
            'C' => isset($avg_hasil_C) ? $avg_hasil_C : null,
            'C1' => isset($avg_hasil_C1) ? $avg_hasil_C1 : null,
            'C2' => isset($avg_hasil_C2) ? $avg_hasil_C2 : null,
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
