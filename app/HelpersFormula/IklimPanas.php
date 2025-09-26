<?php
namespace App\HelpersFormula;
use Carbon\Carbon;
class IklimPanas
{
    public function index($data, $id_parameter, $mdl)
    {
        $c = [];
        $b = [];
        foreach ($data as $val) {
            $pakaian = $val->pakaian_yang_digunakan;
            $nilai_pakaian = 0;
            if ($pakaian == "Pakaian Kerja (kemeja lengan panjang dan celana panjang)") {
                $nilai_pakaian = 0;
            } else if ($pakaian == "Pakaian Kerja (coveralls/wearpack)") {
                $nilai_pakaian = 0;
            } else if ($pakaian == "Pakaian Kerja (coveralls/wearpack) dengan bahan SMS polypropylene") {
                $nilai_pakaian = 3;
            } else if ($pakaian == "Pakaian Kerja (coveralls/wearpack) dengan bahan Polyolefin") {
                $nilai_pakaian = 0.5;
            } else if ($pakaian == "Pakaian Kerja dua rangkap") {
                $nilai_pakaian = 1;
            } else {
                $nilai_pakaian = 11;
            }

            $pengukuran = json_decode($val->pengukuran);

            $nilai_pengukuran = [];
            if ($val->terpapar_panas_matahari == "Iya") {
                foreach ($pengukuran as $key => $value) {
                    array_push($nilai_pengukuran, $value->wbtgc_out);
                }
            } else {
                foreach ($pengukuran as $key => $value) {
                    array_push($nilai_pengukuran, $value->wbtgc_in);
                }
            }
            array_push($c, array_sum($nilai_pengukuran) / count($nilai_pengukuran) + $nilai_pakaian);
            array_push($b, $val->akumulasi_waktu_paparan);
        }

        $hasil_pengukuran = [];
        foreach ($c as $shift => $v) {
            array_push($hasil_pengukuran, $v * $b[$shift]);
        }

        $hasil_final = array_sum($hasil_pengukuran) / array_sum($b);

        // dd($data);

        // $final_isbb = [
        //     'hasil' => $hasil_final,
        //     'total_waktu' => array_sum($b)
        // ];

        $totalShifts = 0;
        $totAvg = [
            'tac_in' => 0,
            'tac_out' => 0,
            'tgc_in' => 0,
            'tgc_out' => 0,
            'wbtgc_in' => 0,
            'wbtgc_out' => 0,
            'wb_in' => 0,
            'wb_out' => 0,
            'rh_in' => 0,
            'rh_out' => 0,
        ];
        $wb_in = 0;
        $wb_out = 0;
        // $dataTotalShift = [];
        foreach ($data as $indx => $val) {
            $shift = explode('-', $val->shift);

            if ($val->pengukuran != null) {

                $dataa = json_decode($val->pengukuran);
                $totData = count(array_keys(get_object_vars($dataa)));

                $shift = [
                    'tac_in' => 0,
                    'tac_out' => 0,
                    'tgc_in' => 0,
                    'tgc_out' => 0,
                    'wbtgc_in' => 0,
                    'wbtgc_out' => 0,
                    'rh_in' => 0,
                    'rh_out' => 0,
                    'wb_in' => 0,
                    'wb_out' => 0,
                ];

                foreach ($dataa as $idx => $vl) {
                    foreach ($vl as $idf => $vale) {
                        $shift[$idf] += $vale;
                    }
                }


                foreach ($shift as $key => $vv) {
                    $shift[$key] = number_format($shift[$key] / $totData, 1);
                    $totAvg[$key] += $shift[$key];
                }
                
                
            }

            $totalShifts++;
        }

        if ($totalShifts > 0) {
            foreach ($totAvg as $keyy => $vv) {
                $totAvg[$keyy] = number_format($totAvg[$keyy] / $totalShifts, 1);
            }
        }

        // Hitung dulu dengan nilai float murni
        $wb_in_a = atan(0.151977 * ($totAvg["rh_in"] + 8.313659) ** 0.5);
        $wb_in_b = atan($totAvg["tac_in"] + $totAvg["rh_in"]);
        $wb_in_c = atan($totAvg["rh_in"] - 1.676331);
        $wb_in_d = 0.00391838 * $totAvg["rh_in"] ** 1.5;
        $wb_in_e = atan(0.023101 * $totAvg["rh_in"]);

        // Total WB_in (float)
        $total_wb_in = $totAvg["tac_in"] * $wb_in_a + $wb_in_b - $wb_in_c + $wb_in_d * $wb_in_e - 4.686035;

        $totAvg['wb_in'] = number_format($total_wb_in, 1);
        
        $wb_out_a = atan(0.151977 * pow($totAvg["rh_out"] + 8.313659, 0.5));
        $wb_out_b = atan($totAvg["tac_out"] + $totAvg["rh_out"]);
        $wb_out_c = atan($totAvg["rh_out"] - 1.676331);
        $wb_out_d = 0.00391838 * pow($totAvg["rh_out"], 1.5);
        $wb_out_e = atan(0.023101 * $totAvg["rh_out"]);

        $total_wb_out = $totAvg["tac_out"] * $wb_out_a + $wb_out_b - $wb_out_c + ($wb_out_d * $wb_out_e) - 4.686035;

        $totAvg['wb_out'] = number_format($total_wb_out, 1);

        // $hasilWb = array (
        //     "wb_in" => array(
        //         "a" => $wb_in_a,
        //         "b" => $wb_in_b,
        //         "c" => $wb_in_c,
        //         "d" => $wb_in_d,
        //         "e" => $wb_in_e,
        //         "Total" => $total_wb_in
        //     ),

        //     "wb_out" => array(
        //         "a" => $wb_out_a,
        //         "b" => $wb_out_b,
        //         "c" => $wb_out_c,
        //         "d" => $wb_out_d,
        //         "e" => $wb_out_e,
        //         "Total" => $total_wb_out
        //     ),
        // );
            
        // dd($totAvg);
        return [
            'hasil' => $hasil_final,
            'hasil_2' => $totAvg
        ];
    }
}