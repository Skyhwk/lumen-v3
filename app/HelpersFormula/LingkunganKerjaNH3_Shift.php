<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupNH3_Shift
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

        foreach($data->ks as $key_ks => $item_ks) {
            foreach ($data->average_flow as $key => $value) {
                // $Vu = \str_replace(",", "",number_format($value * $data->durasi[$key] * (floatval($data->tekanan) / (floatval($data->suhu) + 273)) * (298 / 760), 4));
                // // if($key == 0) dd('Vu : '.$Vu, 'flow :'. $value, 'durasi : '.$data->durasi[$key], 'tekanan : '. $data->tekanan, 'Suhu :'. $data->suhu, 'Avg Penjerapan : '. $item_ks[$key]);
                // if($Vu != 0.0) {
                //     $C = \str_replace(",", "", number_format(($item_ks[$key] / floatval($Vu)) * 1000, 4));
                // }else {
                //     $C = 0;
                // }
                // $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
                // // dd($C1);
                // $C2 = \str_replace(",", "", number_format((floatval($C1) / 48) * 24.45, 5));

                $Vu = \str_replace(",", "",number_format($data->average_flow * $data->durasi * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
                if($Vu != 0.0) {
                    $C = \str_replace(",", "", number_format(($item_ks[$key] / floatval($Vu)) * 1000, 4));
                }else {
                    $C = 0;
                }
                $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
                $C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 17, 5));

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


        if (floatval($C) < 0.1419)
            $C = '<0.1419';
        if (floatval($C1) < 0.0005)
            $C1 = '<0.0005';
        if (floatval($C2) < 0.0007)
            $C2 = '<0.0007';

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
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
            'data_pershift' => [
                'Shift 1' => $C_value[0],
                'Shift 2' => $C_value[1] ?? null,
                'Shift 3' => $C_value[2] ?? null,
            ],
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
