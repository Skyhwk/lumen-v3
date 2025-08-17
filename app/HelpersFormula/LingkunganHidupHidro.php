<?php

namespace App\HelpersFormula;

use Carbon\Carbon;
class LingkunganHidupHidro
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

        $Vs = \str_replace(",", "",number_format(($data->durasi * $data->average_flow) * (298/(273 + $data->suhu)) * ($data->tekanan/760), 4));
        if((int)$Vs <= 0) {
                $C = 0;
                $Qs = 0;
                $C1 = 0;
            }else {
                if ($id_parameter == 261) { 
                    $C = \str_replace(",", "", number_format((((20/19)*($ks - $kb)* 12.5)/$Vs) * 1000, 4));
                    $C1 = \str_replace(",", "", number_format(((20/19)*($ks - $kb)* 12.5)/$Vs, 4));
                    $C2 = \str_replace(",", "", number_format(24.45*($C1/20.01), 4));
                } else if ($id_parameter == 256 || $id_parameter == 568) { 
                    $C = \str_replace(",", "", number_format(((($ks - $kb)* 50 * (36.5/35.5))/$Vs) * 1000000, 1));
                    $C1 = \str_replace(",", "", number_format(((($ks - $kb)* 50 * (36.5/35.5))/$Vs) * 1000, 4));
                    $C2 = \str_replace(",", "", number_format(24.45*($C1/36.5), 4));
                }
            }
        if ($id_parameter == 261) {
            if (floatval($C) < 38.2)
                $C = '<38.2';
            if (floatval($C1) < 0.0382)
                $C1 = '<0.0382';
            if (floatval($C2) < 0.0467)
                $C2 = '<0.0467';
        } else if ($id_parameter == 256 || $id_parameter == 568) {
            if (floatval($C) < 138.4)
                $C = '<138.4';
            if (floatval($C1) < 0.1384)
                $C1 = '<0.1384';
            if (floatval($C2) < 0.0927)
                $C2 = '<0.0927';
        }

        $data = [
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

        return $data;
    }

}