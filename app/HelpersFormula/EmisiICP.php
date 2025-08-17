<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class EmisiICP
{
    public function index($data, $id_parameter, $mdl)
    {
        $Vs = null;
        $Vstd = null;
        $vl = null;
        $st = null;
        $ks = null;
        $kb = null;
        $w1 = null;
        $w2 = null;
        $C = null;
        $C1 = null;
        $C2 = null;

        if (is_array($data->ks)) {
            $ks = array_sum($data->ks) / count($data->ks);
        } else {
            $ks = floatval($data->ks);
        }
        if (is_array($data->kb)) {
            $kb = array_sum($data->kb) / count($data->kb);
        } else {
            $kb = floatval($data->kb);
        }

        $Vstd = str_replace(",", "", number_format(
            $data->flow * pow(((298 * $data->tekanan) / (($data->suhu + 273) * 760)), 0.5) * $data->durasi_dry,
            4
        ));

        $is_4_decimal = [383, 378, 1961];
        $is_5_decimal = [385, 354, 356];

        $decimal = 4; # Default

        if (in_array($id_parameter, $is_4_decimal)) {
            $decimal = 4;
        } elseif (in_array($id_parameter, $is_5_decimal)) {
            $decimal = 5;
        }

        $C = \str_replace(",", "", number_format(((floatval($ks) - floatval($kb)) * floatval($data->vs) * floatval($data->st)) / floatval($Vstd), $decimal));
        $C1 = \str_replace(",", "", number_format(($C / 1000), $decimal));

        if ($id_parameter == 354) {
            $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 108.905), $decimal));
            if ($C < 0.0029)
                $C = '<0.0029';
            if ($C1 < 0.0008)
                $C1 = '<0.0008';
            if (floatval($C2) < 0.000018)
                $C2 = '<0.000018';
        } else if ($id_parameter == 358) {
            $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 51.9961), $decimal));
        } else if ($id_parameter == 378) {
            $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 207.2), $decimal));
        } else if ($id_parameter == 385) {
            $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 65.38), $decimal));
        }

        $vl = $data->vs;
        $st = $data->st;
        // dd($C, $C1, $C2);

        $data = [
            'tanggal_terima' => $data->tanggal_terima,
            'suhu' => $data->suhu,
            'Va' => $data->volume_dry,
            'Vs' => $Vs,
            'Vstd' => $Vstd,
            'Pa' => $data->tekanan,
            'Pm' => $data->tekanan_dry,
            'Pv' => $data->nil_pv,
            't' => $data->durasi_dry,
            'durasi' => $data->durasi_dry,
            'flow' => $data->flow,
            'vl' => $vl,
            'st' => $st,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'w1' => $w1,
            'w2' => $w2,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
        return $data;
    }
}