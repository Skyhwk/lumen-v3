<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganKerjaLogam
{
    public function index($data, $id_parameter, $mdl)
    {
        if($data->use_absorbansi) {
            $ks = array_sum($data->ks[0]) / count($data->ks[0]);
            $kb = array_sum($data->kb[0]) / count($data->kb[0]);
        }else{
            $ks = array_sum($data->ks) / count($data->ks);
            $kb = array_sum($data->kb) / count($data->kb);
            // dd($data);
        }

        $Ta = floatval($data->suhu) + 273;
        $Qs = null;
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
        $C12 = null;
        $C13 = null;
        $C14 = null;
        $C15 = null;
        $C16 = null;
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

        $Vstd = number_format(($data->average_flow * $data->durasi) / 1000, 6);
        // dd(floatval($Vstd) <= 0);
        if (floatval($Vstd) <= 0) {
            $C = 0;
            $Qs = 0;
            $C1 = 0;
        } else {
            $C2_param = ["Hg", "Mn"];
            $C1_C2_C3_param_new = ["As", "Ba", "Cr", "Cu", "Fe"];
            $C2_C3_param = ['Ni'];
            $C1_C2_C3_param = ['Co'];
            $C1_C2_C3_C4_C5_param = ['Cd'];
            if(in_array($data->parameter, $C2_param)) { // C2
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
            } else if(in_array($data->parameter, $C1_C2_C3_param_new)) { // C1, C2 & C3 New
                // C (mg/Nm3) = (((Ct - Cb)*(Vt/1000)*1) / (Vstd*(Pa/Ta)*(298/760))
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / ($Vstd * ($data->tekanan / $Ta) * (298 / 760)));

                // C1 = C2*1000
                $C = ($C1 * 1000);

                if($data->parameter == 'As') {
                    // C (PPM)= (C2 / 24.45)*74,92)
                    $C2 = (($C1 / 24.45) * 74.92);

                    $C14 = $C2;
                    $C15 = $C * 1000;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                }else if($data->parameter == 'Ba') {
                    // C (PPM)= (C2 / 24.45)*137,33)
                    $C2 = (($C1 / 24.45) * 137.33);

                    $C14 = $C2;
                    $C15 = $C * 1000;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                }else if($data->parameter == 'Co') {
                    // C (PPM) = ((((Ct - Cb)*(Vt/1000)*1)/Vstd)*24,45)/58,933
                    $C2 = (((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd) * 24.45 / 58.933);
                }elseif($data->parameter == 'Cr') {
                    // C (PPM)= (C2 / 24.45)*51,996)
                    $C2 = (($C1 / 24.45) * 51.996);

                    $C14 = $C2;
                    $C15 = $C * 1000;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                }elseif($data->parameter == 'Cu') {
                    // C (PPM)= (C2 / 24.45)*63,546)
                    $C2 = (($C1 / 24.45) * 63.546);
                }else if($data->parameter == 'Fe') {
                    // C (PPM)= (C2 / 24.45)*55,845)
                    $C2 = (($C1 / 24.45) * 55.845);
                }
            } else if(in_array($data->parameter, $C2_C3_param)) { // C2, C3
                // C (mg/m3) = ((Ct - Cb)*Vt*1)/Vstd
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);

                // C (PPM) = (C(mg/m3)*24,45)/58,69
                $C2 = (($C1 * 24.45) / 58.69);
            }else if(in_array($data->parameter, $C1_C2_C3_param)) { // C1, C2, C3
                // C (ug/Nm3) = ((Ct - Cb)*Vt*1)/Vstd
                $C = ((($ks - $kb) * $data->vl * 1) / $Vstd);

                // C2 = C1/1000
                $C1 = $C / 1000;

                // C (PPM) = ((((Ct - Cb)*(Vt/1000)*1)/Vstd)*24,45)/58,933
                $C2 = (((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd) * 24.45 / 58.933);
            } else if(in_array($data->parameter, $C1_C2_C3_C4_C5_param)) { // C1 , C2, C3, C4, C5
                // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);

                // C (ug/Nm3) = ((Ct - Cb)*Vt*1)/Vstd
                $C = ((($ks - $kb) * $data->vl * 1) / $Vstd);

                // C (PPM)= (C2 / 24.45)*112,414)
                $C2 = ($C1 / 24.45) * 112.414;

                // C4 = C3 x 1000
                $C3 = $C2 * 1000;

                // C(%) = C3 x 10000
                $C4 = $C2 * 10000;

                $C14 = $C2;
                $C15 = $C;
                $C16 = $C1;
            }

            $C = isset($C) ? number_format($C, 6) : '0.000000';
            $C1 = isset($C1) ? number_format($C1, 6) : '0.000000';
            $C2 = isset($C2) ? number_format($C2, 6) : '0.000000';
            $C3 = isset($C3) ? number_format($C3, 6) : '0.000000';
            $C4 = isset($C4) ? number_format($C4, 6) : '0.000000';
            // MDL Handler
            if(in_array($data->parameter, ['As','Ba'])){
                $C1 = number_format($C1, 6);
                if($C1 < 0.000022){
                    $C1 = "<0.000022";
                }
            }elseif(in_array($data->parameter, ['Cd'])){
                $C1 = number_format($C1, 5);
                if($C1 < 0.00005){
                    $C1 = "<0.00005";
                }
            }elseif(in_array($data->parameter, ['Co'])){
                // "<0,000292 PPM
                // <0,0078 ug/Nm3"
                $C = number_format($C, 4);
                $C2 = number_format($C2, 6);
                if($C < 0.0078){
                    $C = "<0.0078";
                }
                if($C2 < 0.000292){
                    $C2 = "<0.000292";
                }
            }elseif (in_array($data->parameter, ['Cu'])) {
                $C1 = number_format($C1, 5);
                if($C1 < 0.00004){
                    $C1 = "<0.00004";
                }
            }elseif (in_array($data->parameter, ['Fe'])) {
                // <0,000038 mg/m3
                $C1 = number_format($C1, 6);
                if($C1 < 0.000038){
                    $C1 = "<0.000038";
                }
            }elseif(in_array($data->parameter, ['Hg','Mn'])) {
                $C1 = number_format($C1, 4);
                if($C1 < 0.0001){
                    $C1 = "<0.0001";
                }
            }

            $vl = $data->vl;
            // $st = $data->st;
        }

        $satuan = 'mg/m³';
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
            'C' => isset($C) ? $C : null,
            'C1' => isset($C1) ? $C1 : null,
            'C2' => isset($C2) ? $C2 : null,
            'C3' => isset($C3) ? $C3 : null,
            'C4' => isset($C4) ? $C4 : null,
            'C5' => isset($C5) ? $C5 : null,
            'C6' => isset($C6) ? $C6 : null,
            'C7' => isset($C7) ? $C7 : null,
            'C8' => isset($C8) ? $C8 : null,
            'C9' => isset($C9) ? $C9 : null,
            'C10' => isset($C10) ? $C10 : null,
            'C11' => isset($C11) ? $C11 : null,
            'C12' => isset($C12) ? $C12 : null,
            'C13' => isset($C13) ? $C13 : null,
            'C14' => isset($C14) ? $C14 : null,
            'C15' => isset($C15) ? $C15 : null,
            'C16' => isset($C16) ? $C16 : null,
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
