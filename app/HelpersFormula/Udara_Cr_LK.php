<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Udara_Cr_LK
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

        $Vstd = $data->average_flow * $data->durasi;
        if ((int) $Vstd <= 0) {
            $C = 0;
            $Qs = 0;
            $C1 = 0;
        } else {
            $rawC = (($ks - $kb) * $data->vl * $data->st) / $Vstd;
            $rawC1 = $rawC / 1000;
            $C = \str_replace(",", "", number_format($rawC, 4));
            $C1 = \str_replace(",", "", number_format($rawC1, 6));
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
            }
            $vl = $data->vl;
            $st = $data->st;
        }

        // dd($C, $C1, $C2);

        $processed = [
            'hasil1' => $C,
            'satuan' => 'mg/m³'
        ];

        return $processed;
    }
}