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
        $C3 = null;
        $C4 = null;
        $C5 = null;
        $C6 = null;
        $C7 = null;
        $C8 = null;
        $C9 = null;
        $C10 = null;
        $C11 = null;

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

        // VStd (Nm3) = (Rerata Laju Alir TSP (Liter) * durasi) x (298/760) x (Pa/Ta) / 1000
        $Vstd =  number_format(((($data->flow * $data->durasi_dry) * (298 / 760) * ($data->tekanan / ($data->suhu + 273))) / 1000), 4, ".", "");





        // C (ug/Nm3) = (((Ct - Cb)*Vt*S/St)/Vstd)
        $C = number_format((((floatval($ks) - floatval($kb)) * floatval($data->vs) * floatval($data->st)) / floatval($Vstd)), 4, ".", "");
        $C1 = number_format($C / 1000, 4, ".", "");

        // C (ug/m3) = (((Ct - Cb)*Vt*S/St) / (Rerata Laju Alir * t))
        // $C3 = number_format(((floatval($ks) - floatval($kb)) * floatval($data->vs) * floatval($data->st) / (($data->flow * $data->durasi_dry))), 4, ".", "");
        $C3 = $C;

        // $C4 = number_format($C3 / 1000, 4, ".", "");
        $C4 = $C1;


        switch ($data->parameter) {
            case 'As':
                $C2 = number_format(24.45 * ($C1 / 74.92), 4, ".", ""); // ppm
                break;
            case 'Cd':
                $C2 = number_format(24.45 * ($C1 / 112.41), 4, ".", "");  // ppm
                break;
            case 'Co':
                $C2 = number_format(24.45 * ($C1 / 58.93), 4, ".", "");  // ppm
                break;
            case 'Cr':
                $C2 = number_format(24.45 * ($C1 / 51.996), 4, ".", "");  // ppm
                break;
            case 'Cu':
                $C2 = number_format(24.45 * ($C1 / 63.55), 4, ".", "");  // ppm
                break;
            case 'Hg':
                $C2 = number_format(24.45 * ($C1 / 200.59), 4, ".", "");  // ppm
                break;
            case 'Mn':
                $C2 = number_format(24.45 * ($C1 / 54.938), 4, ".", "");  // ppm
                break;
            case 'Pb':
                $C2 = number_format(24.45 * ($C1 / 207.2), 4, ".", "");  // ppm
                break;
            case 'Sb':
                $C2 = number_format(24.45 * ($C1 / 121.76), 4, ".", "");  // ppm
                break;
            case 'Se':
                $C2 = number_format(24.45 * ($C1 / 78.97), 4, ".", "");  // ppm
                break;
            case 'Tl':
                $C2 = number_format(24.45 * ($C1 / 204.38), 4, ".", "");  // ppm
                break;
            case 'Zn':
                $C2 = number_format(24.45 * ($C1 / 65.38), 4, ".", "");  // ppm
                break;
            case 'Sn':
                $C2 = number_format(24.45 * ($C1 / 118.71), 4, ".", "");  // ppm
                break;
            case 'Al':
                $C2 = number_format(24.45 * ($C1 / 26.98), 4, ".", "");  // ppm
                break;
            case 'Ba':
                $C2 = number_format(24.45 * ($C1 / 137.33), 4, ".", "");  // ppm
                break;
            case 'Be':
                $C2 = number_format(24.45 * ($C1 / 9.01), 4, ".", "");  // ppm
                break;

            case 'Bi':
                $C2 = number_format(24.45 * ($C1 / 208.98), 4, ".", "");  // ppm
                break;
            default:
                $C2 = null;  // ppm
                break;
        }





        // $is_4_decimal = [383, 378, 1961];
        // $is_5_decimal = [385, 354, 356];

        // $decimal = 4; # Default

        // if (in_array($id_parameter, $is_4_decimal)) {
        //     $decimal = 4;
        // } elseif (in_array($id_parameter, $is_5_decimal)) {
        //     $decimal = 5;
        // }

        // $C = \str_replace(",", "", number_format(((floatval($ks) - floatval($kb)) * floatval($data->vs) * floatval($data->st)) / floatval($Vstd), $decimal));
        // $C1 = \str_replace(",", "", number_format(($C / 1000), $decimal));

        // if ($id_parameter == 354) {
        //     $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 108.905), $decimal));
        //     if ($C < 0.0029)
        //         $C = '<0.0029';
        //     if ($C1 < 0.0008)
        //         $C1 = '<0.0008';
        //     if (floatval($C2) < 0.000018)
        //         $C2 = '<0.000018';
        // } else if ($id_parameter == 358) {
        //     $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 51.9961), $decimal));
        // } else if ($id_parameter == 378) {
        //     $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 207.2), $decimal));
        // } else if ($id_parameter == 385) {
        //     $C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 65.38), $decimal));
        // }

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
            'C3' => $C3,
            'C4' => $C4,
            'C5' => $C5,
            'C6' => $C6,
            'C7' => $C7,
            'C8' => $C8,
            'C9' => $C9,
            'C10' => $C10,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
        return $data;
    }
}
