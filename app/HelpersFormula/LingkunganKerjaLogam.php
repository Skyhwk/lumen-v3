<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganKerjaLogam
{
    public function index($data, $id_parameter, $mdl)
    {
        if ($data->use_absorbansi) {
            $ks_raw = is_array($data->ks[0]) ? $data->ks[0] : $data->ks;
            $kb_raw = is_array($data->kb[0]) ? $data->kb[0] : $data->kb;
        } else {
            $ks_raw = is_array($data->ks[0]) ? $data->ks[0] : $data->ks;
            $kb_raw = is_array($data->kb[0]) ? $data->kb[0] : $data->kb;
        }

        // Konversi ke float dan format 6 desimal
        $ks_clean = array_map(function ($v) {
            return number_format((float)$v, 6, '.', '');
        }, array_filter($ks_raw, fn($v) => $v !== null && $v !== ''));

        $kb_clean = array_map(function ($v) {
            return number_format((float)$v, 6, '.', '');
        }, array_filter($kb_raw, fn($v) => $v !== null && $v !== ''));

        // Hitung rata-rata (gunakan floatval agar hasil tetap numerik)
        $ks = count($ks_clean) > 0 ? array_sum(array_map('floatval', $ks_clean)) / count($ks_clean) : 0;
        $kb = count($kb_clean) > 0 ? array_sum(array_map('floatval', $kb_clean)) / count($kb_clean) : 0;

        // Format hasil akhir juga ke 6 desimal
        $ks = number_format($ks, 5, '.', '');
        $kb = number_format($kb, 5, '.', '');

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
            $C2_C3_param = ['Ni', 'Sb', 'Se', 'Sn', 'Zn', 'Aluminium (Al)'];
            $C1_C2_C3_param = ['Co'];
            $C1_C2_C3_C4_C5_param = ['Cd'];
            $ICP_aneh = ['Molybdenum (LK)', 'Vanadium (LK)', 'Titanium (LK)'];

            if (in_array($data->parameter, $C2_param)) { // Hg, Mn
                // C (mg/Nm3) = (((Ct - Cb)*(Vt/1000)*1) / (Vstd*(Pa/Ta)*(298/760))
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / ($Vstd * ($data->tekanan / $Ta) * (298 / 760)));

                // C1 = C2*1000
                $C = ($C1 * 1000);

                if ($data->parameter == 'Hg') {
                    // C (PPM)= (C2 / 24.45)*200,59)
                    // revisi menjadi
                    // C (PPM)= (C2 / 200,59)*24.45
                    $C2 = (($C1 / 200.59) * 24.45);
                } else {
                    // C (PPM)= (C2 / 24.45)*54,94)
                    // revisi menjadi
                    // C (PPM)= (C2 / 54,94)*24.45
                    $C2 = (($C1 / 54.94) * 24.45);
                }

                $C14 = $C2;

                // Vstd = (Rerata Laju alir x Durasi Pengambilan sampel)/1000"
                $Vstd_alt = number_format(($data->average_flow * $data->durasi) / 1000, 6);
                // C (mg/Nm3) = (((Ct - Cb)*(Vt/1000)*1) / (Vstd)
                $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd_alt);

                // C16 = C17*1000
                $C15 = ($C16 * 1000);
            } else if (in_array($data->parameter, $C1_C2_C3_param_new)) { // As, Ba, Cr, Cu, Fe
                // C (mg/Nm3) = (((Ct - Cb)*(Vt/1000)*1) / (Vstd*(Pa/Ta)*(298/760))
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / ($Vstd * ($data->tekanan / $Ta) * (298 / 760)));

                // C1 = C2*1000
                $C = ($C1 * 1000);

                if ($data->parameter == 'As') {
                    // C (PPM)= (C2 / 24.45)*74,92)
                    // revisi menjad
                    // C (PPM)= (C2 / 74,92)*24.45
                    $C2 = (($C1 / 74.92) * 24.45);

                    $C14 = $C2;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                    $C15 = $C16 * 1000;
                } else if ($data->parameter == 'Ba') {
                    // C (PPM)= (C2 / 24.45)*137,33)
                    // revisi menjadi
                    // C (PPM)= (C2 / 137,33)*24.45
                    $C2 = (($C1 / 137.33) * 24.45);

                    $C14 = $C2;
                    $C14 = $C2;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                    $C15 = $C16 * 1000;
                } else if ($data->parameter == 'Co') {
                    // C (PPM) = ((((Ct - Cb)*(Vt/1000)*1)/Vstd)*24,45)/58,933
                    $C2 = (((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd) * 24.45 / 58.933);
                } elseif ($data->parameter == 'Cr') {
                    // C (PPM)= (C2 / 24.45)*51,996)
                    // revisi menjadi
                    // C (PPM)= (C2 / 51,996)*24.45
                    $C2 = (($C1 / 51.996) * 24.45);

                    $C14 = $C2;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                    $C15 = $C16 * 1000;
                } elseif ($data->parameter == 'Cu') {
                    // C (PPM)= (C2 / 24.45)*63,546)
                    // revisi menjadi
                    // C (PPM)= (C2 / 63,546)*24.45
                    $C2 = (($C1 / 63.546) * 24.45);

                    $C14 = $C2;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                    $C15 = $C16 * 1000;
                } else if ($data->parameter == 'Fe') {
                    // C (PPM)= (C2 / 24.45)*55,845)
                    // revisi menjadi
                    // C (PPM)= (C2 / 55,845)*24.45
                    $C2 = (($C1 / 55.845) * 24.45);

                    $C14 = $C2;

                    // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                    $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                    $C15 = $C16 * 1000;
                }
            } else if (in_array($data->parameter, $C2_C3_param)) { // Ni, Sb, Se, Sn, Zn, Al
                // C (mg/Nm3) = (((Ct - Cb)*(Vt/1000)*1) / (Vstd*(Pa/Ta)*(298/760))
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / ($Vstd * ($data->tekanan / $Ta) * (298 / 760)));

                // C1 = C2 * 1000
                $C = ($C1 * 1000);

                if ($data->parameter == 'Ni') {
                    // C (PPM) = (C(mg/m3)/24,45)*58,69
                    // revisi menjadi
                    // C (PPM)= (C2 / 58,69)*24.45
                    $C2 = (($C1 / 58.69) * 24.45);
                } else if ($data->parameter == 'Sb') {
                    // C (PPM)= (C2 / 24.45)*121,76)
                    // revisi menjadi
                    // C (PPM)= (C2 / 121,76)*24.45
                    $C2 = (($C1 / 121.76) * 24.45);
                } else if ($data->parameter == 'Se') {
                    // C (PPM)= (C2 / 24.45)*78,97)
                    // revisi menjadi
                    // C (PPM)= (C2 / 78,97)*24.45)
                    $C2 = (($C1 / 78.97) * 24.45);
                } else if ($data->parameter == 'Sn') {
                    // C (PPM)= (C2 / 24.45)*118,71)
                    // revisi menjadi
                    // C (PPM)= (C2 / 118,71)*24.45
                    $C2 = (($C1 / 118.71) * 24.45);
                } else if ($data->parameter == 'Zn') {
                    // C (PPM)= (C2 / 24.45)*65,38)
                    // revisi menjadi
                    // C (PPM)= (C2 / 65,38)*24.45)
                    $C2 = (($C1 / 65.38) * 24.45);
                } else {
                    // C (PPM)= (C2 / 24.45)*26,98)
                    $C2 = (($C1 / 24.45) * 26.98);
                }

                $C14 = $C2;

                // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);

                $C15 = $C16 * 1000;
            } else if (in_array($data->parameter, $C1_C2_C3_param)) { // Co
                // C (ug/Nm3) = ((Ct - Cb)*Vt*1)/Vstd
                $C = ((($ks - $kb) * $data->vl * 1) / $Vstd);

                // C2 = C1/1000
                $C1 = $C / 1000;

                // C (PPM) = ((((Ct - Cb)*(Vt/1000)*1)/Vstd)*24,45)/58,933
                // revisi menjadi
                // C (PPM)= (C2 / 58,933) * 24.45
                $C2 = ($C1 * 24.45 / 58.933);

                $C14 = $C2;

                // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                $C15 = $C16 * 1000;
            } else if (in_array($data->parameter, $C1_C2_C3_C4_C5_param)) { // Cd
                // Vstd (Nm3) = (Q*([(298*P0)/((T0+273)*760)]^0,5))*t
                $Vstd_with_pa = round($Vstd * ($data->tekanan / $Ta) * (298 / 760), 6);
                // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd_with_pa);

                // C1 = C2*1000
                $C = $C1 * 1000;

                // C (PPM)= (C2 / 24.45)*112,414)
                // revisi menjadi
                // C (PPM)= (C2 / 112,414)*24.45
                $C2 = ($C1 / 112.414) * 24.45;

                // C4 = C3 x 1000
                $C3 = $C2 * 1000;

                // C(%) = C3 x 10000
                $C4 = $C2 * 10000;

                $C14 = $C2;

                // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                $C15 = $C16 * 1000;
            } else if (in_array($data->parameter, $ICP_aneh)) {
                // C (mg/Nm3) = (((Ct - Cb)*(Vt/1000)*1) / (Vstd*(Pa/Ta)*(298/760))
                $C1 = ((($ks - $kb) * ($data->vl / 1000) * 1) / ($Vstd / ($data->tekanan / $Ta) * (298 / 760)));

                // C1 = C2*1000
                $C = ($C1 * 1000);

                // C (PPM)= (C2 / 24.45)*74,92)
                // revisi menjadi
                // C (PPM)= (C2 / 74,92)*24.45)
                $C2 = ($C1 / 74.92) * 24.45;

                $C14 = $C2;

                // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                $C15 = $C16 * 1000;
            }

            if ($data->parameter == 'Pb') {
                // Vstd (Nm3) = (Q*([(298*P0)/((T0+273)*760)]^0,5))*t
                $Vstd_alt = round((($data->nilQs * ((298 * $data->tekanan) / ($Ta * 760))) ** 0.5) * $data->durasi, 6);

                // C (mg/Nm3) = (((Ct - Cb)*Vt*(S/St))/Vstd)/1000
                $C1 = (($ks - $kb) * $data->vl * ($data->st / $Vstd_alt) / 1000);

                // "C1 (ug/Nm3) = C2 * 1000"
                $C = $C1 * 1000;

                // C (PPM)= (C2 / 24.45)*207,2)
                // revisi menjadi
                // C (PPM)= (C2 / 207,2)*24.45
                $C2 = ($C1 / 207.2) * 24.45;

                $C14 = $C2;

                // C (mg/m3) = (((Ct - Cb)*(Vt/1000)*1)/Vstd)
                $C16 = ((($ks - $kb) * ($data->vl / 1000) * 1) / $Vstd);
                $C15 = $C16 * 1000;
            }

            $C = isset($C) ? number_format($C, 6) : '0.000000';
            $C1 = isset($C1) ? number_format($C1, 6) : '0.000000';
            $C2 = isset($C2) ? number_format($C2, 6) : '0.000000';
            $C3 = isset($C3) ? number_format($C3, 6) : '0.000000';
            $C4 = isset($C4) ? number_format($C4, 6) : '0.000000';
            $C14 = isset($C14) ? number_format($C14, 6) : '0.000000';
            $C15 = isset($C15) ? number_format($C15, 6) : '0.000000';
            $C16 = isset($C16) ? number_format($C16, 6) : '0.000000';
            // MDL Handler
            if (in_array($data->parameter, ['As', 'Ba'])) {
                $C1 = number_format($C1, 6);
            } elseif (in_array($data->parameter, ['Cd'])) {
                $C1 = number_format($C1, 5);

                $C = number_format($C, 5);

                $C2 = number_format($C2, 6);
            } elseif (in_array($data->parameter, ['Co'])) {
                // "<0,000292 PPM
                // <0,0078 ug/Nm3"
                $C = number_format($C, 4);
                $C2 = number_format($C2, 6);
            } elseif (in_array($data->parameter, ['Cu'])) {
                $C1 = number_format($C1, 5);
            } elseif (in_array($data->parameter, ['Fe'])) {
                // <0,000038 mg/m3
                $C1 = number_format($C1, 6);
            } elseif (in_array($data->parameter, ['Hg', 'Mn'])) {
                $C1 = number_format($C1, 4);
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
