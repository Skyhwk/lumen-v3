<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganKerjaNH3_Shift
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

        $C_value = $C1_value = $C2_value = $C3_value = $C4_value = $C14_value = $C15_value = $C16_value = [];

        // dd($data->durasi);
        foreach($data->ks as $key_ks => $item_ks) {
            $Ta = floatval($data->suhu_array[$key_ks]) + 273;
            $Vu = \str_replace(",", "",number_format($data->average_flow * $data->durasi * (floatval($data->tekanan_array[$key_ks]) / $Ta) * (298 / 760), 4));
            if($Vu != 0.0) {
                $C = \str_replace(",", "", number_format(($item_ks / floatval($Vu)) * 1000, 4));
            }else {
                $C = 0;
            }
            $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
            $C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 17.031, 5));
            $C3 = $C2 * 1000;
            $C4 = $C3 * 10000;

            // if($data->parameter == 'NH3 (24 Jam)' || $data->tipe_data == 'ambient'){
            $C14 = $C2;

            // Vu = Rerata Laju Alir*t*/1000
            $Vu_alt = \str_replace(",", "",number_format($data->average_flow * $data->durasi, 4));
            // C (mg/Nm3) = (a/Vu)
            $C15 = \str_replace(",", "", number_format($item_ks / floatval($Vu_alt) * 1000, 4));
            $C16 = $C15 / 1000;

            $C14_value[$key_ks][] = $C14;
            $C15_value[$key_ks][] = $C15;
            $C16_value[$key_ks][] = $C16;
            // }

            $C_value[$key_ks][] = $C;
            $C1_value[$key_ks][] = $C1;
            $C2_value[$key_ks][] = $C2;
            $C3_value[$key_ks][] = $C3;
            $C4_value[$key_ks][] = $C4;
        }

        $C = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C_value);

        $C_average = number_format(array_sum($C) / count($C), 4);

        $C1 = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C1_value);

        $C1_average = number_format(array_sum($C1) / count($C1), 4);

        $C2 = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C2_value);

        $C2_average = number_format(array_sum($C2) / count($C2), 4);

        $C3 = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C3_value);

        $C3_average = number_format(array_sum($C3) / count($C3), 4);

        $C4 = array_map(function ($value) {
            return number_format(array_sum($value) / count($value), 4);
        }, $C4_value);

        $C4_average = number_format(array_sum($C4) / count($C4), 4);

        if($data->parameter == 'NH3 (24 Jam)' || $data->tipe_data == 'ambient'){
            $C14 = array_map(function ($value) {
                return number_format(array_sum($value) / count($value), 4);
            }, $C14_value);

            $C14_average = number_format(array_sum($C14) / count($C14), 4);

            $C15 = array_map(function ($value) {
                return number_format(array_sum($value) / count($value), 4);
            }, $C15_value);

            $C15_average = number_format(array_sum($C15) / count($C15), 4);

            $C16 = array_map(function ($value) {
                return number_format(array_sum($value) / count($value), 4);
            }, $C16_value);

            $C16_average = number_format(array_sum($C16) / count($C16), 4);
        }

        $data_pershift = [
            'Shift 1' => $C[0],
            'Shift 2' => $C[1] ?? null,
            'Shift 3' => $C[2] ?? null,
        ];

        if($data->parameter === 'NH3 (24 Jam)'){
            $data_pershift = [
                'Shift 1' => $C[0],
                'Shift 2' => $C[1] ?? null,
                'Shift 3' => $C[2] ?? null,
                'Shift 4' => $C[3] ?? null,
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
            'C' => $C_average,
            'C1' => $C1_average,
            'C2' => $C2_average,
            'C3' => $C3_average,
            'C4' => $C4_average,
            'C14' => $C14_average ?? null,
            'C15' => $C15_average ?? null,
            'C16' => $C16_average ?? null,
            'data_pershift' => $data_pershift,
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
