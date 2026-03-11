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
        $C14 = null;
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
        $data_pershift = null;


        $C = $C15 = $C16 = [];

        foreach($data->ks as $key => $value) {
            $Vstd = round(($data->array_qs[$key] * $data->durasi_array[$key]) / 1000, 4);
            if ((float) $Vstd <= 0) {
                $rawC = 0;
                $Qs = 0;
                $result = 0;
            } else {
                $rawC = (($value - $data->kb[$key]) * $data->vl * $data->st) / $Vstd;

                $result = round($rawC, 4);
            }
            $Vstd_alt = round(($data->flow_array[$key] * $data->durasi_array[$key]) / 1000, 4);
            if ((float) $Vstd_alt <= 0) {
                $C15_result = 0;
            } else {
                $rawC15 = (($value - $data->kb[$key]) * $data->vl * $data->st) / $Vstd_alt;
                $C15_result = round($rawC15, 4);
            }

            $C16_result = $result / 1000;
            
            array_push($C, $result);
            array_push($C15, $C15_result);
            array_push($C16, round($C16_result, 4));
        }
        $vl = $data->vl;

        $C = count($C) > 0 ? round(array_sum($C) / count($C), 4) : 0;
        $C15 = count($C15) > 0 ? round(array_sum($C15) / count($C15), 4) : 0;
        $C16 = count($C16) > 0 ? round(array_sum($C16) / count($C16), 4) : 0;

        $satuan = 'ug/Nm3';

        $data_pershift = [
            'Shift 1' => $C[0] ?? null,
            'Shift 2' => $C[1] ?? null,
            'Shift 3' => $C[2] ?? null
        ];

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
            'C14' => $C14,
            'C15' => $C15,
            'C16' => $C16,
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
