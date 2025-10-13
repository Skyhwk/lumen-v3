<?php

namespace App\HelpersFormula;

use Carbon\Carbon;
class LingkunganHidupNO2_8J
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

        $Vu = \str_replace(",", "",number_format($data->average_flow * $data->durasi * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
        // dd($Vu);
        $hasil1_array = [];
        $hasil2_array = [];
        $hasil3_array = [];

        foreach ($data->ks as $key => $value) {
            if($Vu != 0.0) {
                $C_value = \str_replace(",", "", number_format(($value / floatval($Vu)) * (10 / 25) * 1000, 4));
            }else {
                $C_value = 0;
            }
            $C1_value = \str_replace(",", "", number_format(floatval($C_value) / 1000, 5));
            $C2_value = \str_replace(",", "", number_format(24.45 * floatval($C1_value) / 46, 5));

            array_push($hasil1_array, $C_value);
            array_push($hasil2_array, $C1_value);
            array_push($hasil3_array, $C2_value);
        }
        $C = array_sum($hasil1_array) / count($hasil1_array);
        $C1 = array_sum($hasil2_array) / count($hasil2_array);
        $C2 = array_sum($hasil3_array) / count($hasil3_array);

        if (floatval($C) < 0.4623)
            $C = '<0.4623';
        if (floatval($C1) < 0.00046)
            $C1 = '<0.00046';
        if (floatval($C2) < 0.00025)
            $C2 = '<0.00025';

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
                'Shift 1' => $hasil1_array[0],
                'Shift 2' => $hasil1_array[1] ?? null,
                'Shift 3' => $hasil1_array[2] ?? null,
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
