<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupLogam
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

        
        $is_4_decimal = [234,287,305,195,199,207,219,262,321,322,325,2104,2105];
        $is_5_decimal = [212,220,292,351];

        $decimal = 4;
        if (in_array($id_parameter, $is_4_decimal)) {
            $decimal = 4;
        } else if (in_array($id_parameter, $is_5_decimal)) {
            $decimal = 5;
        }
        
        $Vstd = number_format(($data->average_flow * $data->durasi) / 1000, 1);
        if ((float) $Vstd <= 0) {
            $C = 0;
            $Qs = 0;
            $C1 = 0;
        } else {
            $rawC = (($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd;

            $C = \str_replace(",", "", number_format($rawC, $decimal));
            $satuan = 'mg/m³';
            if($id_parameter == 2104){ # Co (LK-pm)
                $C = number_format(($rawC * 24.45) / 58.933, 4);
                $satuan = 'ppm';
            }else if ($id_parameter == 2105 && $data->id_parameter == 2114) { # Co (LK-µg) & Cu (LK)
                $rawC = (($ks - $kb) * $data->vl * 1) / $Vstd;

                $C = \str_replace(",", "", number_format($rawC, $decimal));
                $satuan = 'µg/Nm³';
            }
            $vl = $data->vl;
        }

        if(!is_null($mdl) && $C < $mdl) {
            $C = '<'.$mdl;
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
            'hasil1' => $C,
            'hasil2' => $C1,
            'hasil3' => $C2,
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