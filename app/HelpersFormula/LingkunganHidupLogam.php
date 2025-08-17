<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupLogam
{
    public function index($data, $id_parameter, $mdl)
    {
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


        $is_4_decimal = [234, 287, 305];
        $is_5_decimal = [199, 207, 212, 219, 220, 292, 351];

        $decimal = 4;
        if (in_array($id_parameter, $is_4_decimal)) {
            $decimal = 4;
        } else if (in_array($id_parameter, $is_5_decimal)) {
            $decimal = 5;
        }

        $Vstd = $data->average_flow * $data->durasi;
        if ((int) $Vstd <= 0) {
            $C = 0;
            $Qs = 0;
            $C1 = 0;
        } else {
            $rawC = (($ks - $kb) * $data->vl * $data->st) / $Vstd;
            $rawC1 = $rawC / 1000;

            $C = \str_replace(",", "", number_format($rawC, $decimal));
            $C1 = \str_replace(",", "", number_format($rawC1, 6));
            $satuan = 'mg/m³';
            if ($id_parameter == 234 || $id_parameter == 569) { // Fe Udara
                $rawC2 = $rawC1 * 24.45 / 55.845;
                $C2 = \str_replace(",", "", number_format($rawC2, 7));
            } else if ($id_parameter == 287) { // Mn Udara
                $rawC2 = $rawC1 * 24.45 / 55.845;
                $C2 = \str_replace(",", "", number_format($rawC2, 7));
            } else if ($id_parameter == 292) {
                $rawC2 = $rawC1 * 24.45 / 58.6934;
                $C2 = \str_replace(",", "", number_format($rawC2, 7));
            } else if ($id_parameter == 219) {
                $rawC2 = $rawC1 * 24.45 / 51.9961;
                $C2 = \str_replace(",", "", number_format($rawC2, 7));
            } else if ($id_parameter == 305 || $id_parameter == 306 || $id_parameter == 307 || $id_parameter == 308) {
                $rawC2 = $rawC1 * 24.45 / 207.2;
                $C2 = \str_replace(",", "", number_format($rawC2, 7));
                if (floatval($C) < 0.0128)
                    $C = '<0.0128';
                if (floatval($C1) < 0.000013)
                    $C1 = '<0.000013';
                if (floatval($C2) < 0.0000014)
                    $C2 = '<0.0000014';
            }
            $vl = $data->vl;
            $st = $data->st;
        }

        // dd($C, $C1, $C2);

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
            // 'hasil1' => $C,
            // 'hasil2' => $C1,
            // 'hasil3' => $C2,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
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