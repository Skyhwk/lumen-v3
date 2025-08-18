<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupO3
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

        $Ta = floatval($data->suhu) + 273;

        $C_value = [];
        $C1_value = [];
        $C2_value = [];
        // dd($data->average_flow);
        foreach ($data->average_flow as $key => $value) {
            $Vu = \str_replace(",", "",number_format($value * $data->durasi[$key] * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
            if($Vu != 0.0) {
                $C = \str_replace(",", "", number_format(($ks[$key] / floatval($Vu)) * 1000, 4));
            }else {
                $C = 0;
            }
            $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
            $C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 48, 5));

            array_push($C_value, $C);
            array_push($C1_value, $C1);
            array_push($C2_value, $C2);
        }

        $hasil1 = $C_value[0];
        $hasil2 = $C_value[1];
        $avg_hasil = array_sum($C_value) / count($C_value);

        if(!is_null($mdl) && $avg_hasil < $mdl) {
            $avg_hasil = '<'.$mdl;
        }

        $satuan = 'µg/Nm³';

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
            'hasil1' => $avg_hasil,
            'hasil2' => $hasil1,
            'hasil3' => $hasil2,
            'hasil4' => null,
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