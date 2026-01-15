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

        $C_value = $C1_value = $C2_value = $C14_value = $C15_value = $C16_value = [];

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

                $C14 = $C2;
                $Vu_alt = \str_replace(",", "", number_format($value * $data->durasi[$key], 4));
                $C16 = \str_replace(",", "", number_format((floatval($item_ks[$key]) / floatval($Vu_alt)) * 1000, 5));
                $C15 = $C16;

                $C_value[$key_ks][$key] = $C;
                $C1_value[$key_ks][$key] = $C1;
                $C2_value[$key_ks][$key] = $C2;

                $C14_value[$key_ks][$key] = $C14;
                $C15_value[$key_ks][$key] = $C15;
                $C16_value[$key_ks][$key] = $C16;
            }
        }

        $C_average = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C_value);

        $C = number_format(array_sum($C_average) / count($C_average), 5);

        $C1_average = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C1_value);

        $C1 = number_format(array_sum($C1_average) / count($C1_average), 5);

        $C2_average = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C2_value);

        $C2 = number_format(array_sum($C2_average) / count($C2_average), 5);

        $C14_average = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C14_value);

        $C14 = number_format(array_sum($C14_average) / count($C14_average), 5);

        $C15_average = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C15_value);

        $C15 = number_format(array_sum($C15_average) / count($C15_average), 5);

        $C16_average = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C16_value);

        $C16 = number_format(array_sum($C16_average) / count($C16_average), 5);

        $satuan = 'mg/Nm3';


        // dd($avg_pershift);
        $processed = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => round(array_sum($data->average_flow) / count($data->average_flow),4),
            'durasi' => round(array_sum($data->durasi) / count($data->durasi), 4),
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
            'C3' => null,
            'C4' => null,
            'C5' => null,
            'C6' => null,
            'C7' => null,
            'C8' => null,
            'C9' => null,
            'C10' => null,
            'C11' => null,
            'C12' => null,
            'C13' => null,
            'C14' => $C14,
            'C15' => $C15,
            'C16' => $C16,
            'data_pershift' => [
                'Shift 1' => $C_average[0],
                'Shift 2' => $C_average[1] ?? null,
                'Shift 3' => $C_average[2] ?? null
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
