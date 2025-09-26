<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupO3_8J
{
    public function index($data, $id_parameter, $mdl)
    {
        // $ks = null;
        $raw_ks = collect($data->ks)->map(function ($item) {
            return array_sum($item) / count($item);
        })->toArray();

        $raw_kb = collect($data->kb)->map(function ($item) {
            return array_sum($item) / count($item);
        })->toArray();

        $ks = number_format(array_sum($raw_ks) / count($raw_ks), 5);
        $kb = number_format(array_sum($raw_kb) / count($raw_kb), 5);

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

        $Ta = floatval($data->suhu) + 273;

        $C_value = [];
        $C1_value = [];
        $C2_value = [];

        // dd($data->ks);
        foreach ($data->ks as $key_ks => $item_ks) {
            foreach ($data->average_flow as $key => $value) {
                $Vu = \str_replace(",", "", number_format($value * $data->durasi[$key] * (floatval($data->tekanan) / (floatval($data->suhu) + 273)) * (298 / 760), 4));
                // if($key == 0) dd('Vu : '.$Vu, 'flow :'. $value, 'durasi : '.$data->durasi[$key], 'tekanan : '. $data->tekanan, 'Suhu :'. $data->suhu, 'Avg Penjerapan : '. $item_ks[$key]);
                if ($Vu != 0.0) {
                    $C = \str_replace(",", "", number_format(($item_ks[$key] / floatval($Vu)) * 1000, 4));
                } else {
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
        }, $data->parameter == 'O3 8J (LK-pm)' ? $C2_value : $C1_value);

        $avg_hasil = number_format(array_sum($avg_pershift) / count($avg_pershift), 5);

        $satuan = 'mg/m3';
        if ($data->parameter == 'O3 8J (LK-pm)') {
            $satuan = 'ppm';
        }

        if (!is_null($mdl) && $avg_hasil < $mdl) {
            $avg_hasil = '<' . $mdl;
        }

        // dd($avg_pershift);
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
