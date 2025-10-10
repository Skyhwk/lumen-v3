<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupH2S_UA
{
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        $kb = null;

        if($data->use_absorbansi) {
            $ks = array_sum($data->ks[0]) / count($data->ks[0]);
            $kb = array_sum($data->kb[0]) / count($data->kb[0]);
        }else{
            $ks = array_sum($data->ks) / count($data->ks);
            $kb = array_sum($data->kb) / count($data->kb);
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
        if($Vu != 0.0) {
            $C_ = \str_replace(",", "", number_format(($ks - $kb) / floatval($Vu), 4));
        }else {
            $C_ = 0;
        }

        $C_ = \str_replace(",", "", number_format((floatval($ks) - floatval($kb)) / (floatval($Vu) != 0.0 ? floatval($Vu) : 1), 4));
        $C = \str_replace(",", "", number_format(floatval($C_) * (34 / 24.45), 4));

        if (!is_null($mdl) && $C < $mdl){
            $C = "<$mdl";
        }

        $satuan = 'µg/Nm³';

        $processed = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => array_sum($data->average_flow) / count($data->average_flow),
            'durasi' => array_sum($data->durasi) / count($data->durasi),
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
