<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupO3_PM
{
    public function index($data, $id_parameter, $mdl) {
        // $ks = null;
        // dd(count($data->ks));
        if($data->use_absorbansi) {
            $ks = array_sum($data->ks[0]) / count($data->ks[0]);
            $kb = array_sum($data->kb[0]) / count($data->kb[0]);
        }else{
            $ks = array_sum($data->ks) / count($data->ks);
            $kb = array_sum($data->kb) / count($data->kb);
        }

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

        $Ta = floatval($data->suhu) + 273;

        $C_value = [];
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

                // if (floatval($C) < 0.1419)
                //     $C = '<0.1419';
                // if (floatval($C1) < 0.00014)
                //     $C1 = '<0.00014';
                // if (floatval($C2) < 0.00007)
                //     $C2 = '<0.00007';

                $C_value[$key_ks][$key] = $C;
                $C1_value[$key_ks][$key] = $C1;
                $C2_value[$key_ks][$key] = $C2;
            }
        }

        $avg_pershift = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C2_value);

        // dd($avg_pershift);
        $avg_hasil = array_sum($avg_pershift) / count($avg_pershift);

        $satuan = 'ppm';

        if(!is_null($mdl) && $avg_hasil < $mdl){
            $avg_hasil = '<'. $mdl;
        }

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
            'hasil1' => $avg_hasil,
            'hasil2' => $avg_pershift[0],
            'hasil3' => $avg_pershift[1] ?? null,
            'hasil4' => $avg_pershift[2] ?? null,
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