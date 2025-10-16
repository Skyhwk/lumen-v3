<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupLogam_8J
{
    public function index($data, $id_parameter, $mdl)
    {
        // dd($data);
        $ks = null;
        if (is_array($data->ks)) {
            $ks = number_format(array_sum($data->ks) / count($data->ks), 4);
        } else {
            $ks = $data->ks;
        }
        $kb = null;
        if (is_array($data->kb)) {
            $kb = number_format(array_sum($data->kb) / count($data->kb), 4);
        } else {
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
        $satuan = '';

        $arr_hasil = [];

        $Vstd = round(($data->average_flow * $data->durasi) / 1000, 1);
        if ((float) $Vstd <= 0) {
            $C = 0;
            $Qs = 0;
            $C1 = 0;
        } else {
            foreach($data->ks as $key => $value) {
                if($data->tipe_data == 'ambient'){
                    $rawC = (($value - $data->kb[$key]) * ($data->vl / 1000) * 1) / $Vstd;
                }else if($data->tipe_data == 'ulk'){
                    $rawC = (($value - $data->kb[$key]) * $data->vl * $data->st) / $Vstd;
                }

                $result = round($rawC, 4);

                array_push($arr_hasil, $result);
            }
        }
        $vl = $data->vl;

        // tipe data = ambient, ulk, volatile
        if($data->tipe_data == 'ulk'){
            $C1 = count($arr_hasil) > 0 ? round(array_sum($arr_hasil) / count($arr_hasil), 4) : 0;

            if(!is_null($mdl) && $C1 < 0.000013) {
                $C1 = '<0.000013';
            }

            $satuan = 'mg/m³';
        }else if($data->tipe_data == 'ambient') {
            $C = count($arr_hasil) > 0 ? round(array_sum($arr_hasil) / count($arr_hasil), 4) : 0;

            if(!is_null($mdl) && $C < 0.0128) {
                $C = '<0.0128';
            }
            $satuan = 'ug/Nm³';
        }

        $data_pershift = [
            'Shift 1' => $arr_hasil[0] ?? null,
            'Shift 2' => $arr_hasil[1] ?? null,
            'Shift 3' => $arr_hasil[2] ?? null
        ];

        if(in_array($data->parameter, ['Pb (24 Jam)'])){
            $data_pershift = [
                'Shift 1' => $arr_hasil[0] ?? null,
                'Shift 2' => $arr_hasil[1] ?? null,
                'Shift 3' => $arr_hasil[2] ?? null,
                'Shift 4' => $arr_hasil[3] ?? null
            ];
        }

        // dd($C, $C1, $C2);

        $processed = [
            // 'tanggal_terima' => $data->tanggal_terima,
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
            // 'hasil1' => $C,
            // 'hasil2' => $C1,
            // 'hasil3' => $C2,
            'data_pershift' => $data_pershift,
            'satuan' => $satuan,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
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
