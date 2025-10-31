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
        $C2 = number_format(((floatval($ks) - floatval($kb)) * floatval($data->vs) * floatval($data->st) / (($data->flow * $data->durasi_dry))), 4, ".", "");

        $C3 = number_format($C2 / 1000, 4, ".", "");


        switch ($data->parameter) {
            case 'As':
                $C4 = number_format(24.45 * ($C / 74.92), 4, ".", "");
                #MDL
                if ($C3 < 0.01) {
                    $C3 = "<0.01";
                }
                if ($C4 < 0.0041) {
                    $C4 = "<0.0041";
                }
                break;
            case 'Cd':
                $C4 = number_format(24.45 * ($C / 112.41), 4, ".", "");
                #MDL
                if ($C3 < 0.0008) {
                    $C3 = "<0.0008";
                }
                break;
            case 'Co':
                $C4 = number_format(24.45 * ($C / 28.01), 4, ".", "");
                #MDL
                if ($C3 < 0.0075) {
                    $C3 = "<0.0075";
                }
                if ($C4 < 0.0031) {
                    $C4 = "<0.0031";
                }
                break;
            case 'Cr':
                $C4 = number_format(24.45 * ($C / 51.996), 4, ".", "");
                #MDL
                if ($C3 < 0.0111) {
                    $C3 = "<0.0111";
                }
                break;
            case 'Cu':
                $C4 = number_format(24.45 * ($C / 63.55), 4, ".", "");
                #MDL
                if ($C3 < 0.0031) {
                    $C3 = "<0.0031";
                }
                break;
            case 'Hg':
                $C4 = number_format(24.45 * ($C / 200.59), 4, ".", "");
                #MDL
                if ($C3 < 0.01) {
                    $C3 = "<0.01";
                }
                if ($C4 < 0.0004) {
                    $C4 = "<0.0004";
                }
                break;
            case 'Mn':
                $C4 = number_format(24.45 * ($C / 54.938), 4, ".", "");
                break;
            case 'Pb':
                $C4 = number_format(24.45 * ($C / 207.2), 4, ".", "");
                #MDL
                if ($C3 < 0.0042) {
                    $C3 = "<0.0042";
                }
                break;
            case 'Sb':
                $C4 = number_format(24.45 * ($C / 121.76), 4, ".", "");
                #MDL
                if ($C3 < 0.01) {
                    $C3 = "<0.01";
                }
                if ($C4 < 0.0016) {
                    $C4 = "<0.0016";
                }
                break;
            case 'Se':
                $C4 = number_format(24.45 * ($C / 78.97), 4, ".", "");
                #MDL
                if ($C3 < 0.01) {
                    $C3 = "<0.01";
                }
                if ($C4 < 0.0016) {
                    $C4 = "<0.0016";
                }
                break;
            case 'Tl':
                $C4 = number_format(24.45 * ($C / 204.38), 4, ".", "");
                #MDL
                if ($C3 < 0.0037) {
                    $C3 = "<0.0037";
                }
                break;
            case 'Zn':
                $C4 = number_format(24.45 * ($C / 65.38), 4, ".", "");
                #MDL
                if ($C3 < 0.0035) {
                    $C3 = "<0.0035";
                }
                if ($C4 < 0.0035) {
                    $C4 = "<0.0035";
                }
                break;
            case 'Sn':
                $C4 = number_format(24.45 * ($C / 118.71), 4, ".", "");
                #MDL
                if ($C3 < 0.01) {
                    $C3 = "<0.01";
                }
                break;
            case 'Al':
                $C4 = number_format(24.45 * ($C / 26.98), 4, ".", "");
                break;
            case 'Ba':
                $C4 = number_format(24.45 * ($C / 137.33), 4, ".", "");
                break;
            case 'Be':
                $C4 = number_format(24.45 * ($C / 9.01), 4, ".", "");
                break;

            case 'Bi':
                $C4 = number_format(24.45 * ($C / 208.98), 4, ".", "");
                break;
            default:
                $C4 = null;
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
