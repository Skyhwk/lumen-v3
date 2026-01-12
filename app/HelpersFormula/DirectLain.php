<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLain {
    public function index($data, $id_parameter, $mdl) {
        $totalNilai = 0;
        $jumlahElemen = 0;
        $ta = $data->pluck('suhu')->toArray();
        $pa = $data->pluck('tekanan_udara')->toArray();

        foreach ($data as $dataItem) {
            $pengukuran = json_decode($dataItem->pengukuran, true);
            foreach ($pengukuran as $value) {
                $totalNilai += floatval($value);
                $jumlahElemen++;
            }
        }

        $tekanan_udara = !empty($pa) ? number_format(array_sum($pa) / count($pa), 1) : 0;
        $suhu = !empty($ta) ? number_format(array_sum($ta) / count($ta), 1) : 0;

        // Inisialisasi default
        $c1 = $c2 = $c3 = $c4 = $c5 = $c15 = $c16 = $c17 = NULL;
        $satuan = NULL;

        // Daftar parameter
        $paramO2  = ["O2"];
        $paramCO2 = ["CO2", "CO2 (24 Jam)", "CO2 (8 Jam)" , "CO2 8J (LK)", "CO2 (UA)"];
        $paramVoc = ["VOC", "VOC (8 Jam)"];
        $paramCO  = ["C O", "CO (8 Jam)", "CO (6 Jam)", "CO (24 Jam)", "CO 6J", "CO (UA)"];

        // Hanya proses kalau jumlah data valid
        if ($jumlahElemen > 0) {
            foreach ($data as $row) {
                if (in_array($row->parameter, $paramCO)) {
                    // ==========================
                    // HITUNG NILAI MENTAH
                    // ==========================

                    $c3_raw = $totalNilai / $jumlahElemen; // ppm

                    $c2_raw = (($c3_raw * 28.01) / 24.45)
                                * ($suhu / $tekanan_udara)
                                * (298 / 760);

                    $c1_raw  = $c2_raw * 1000;
                    $c4_raw  = $c3_raw * 1000;
                    $c5_raw  = $c3_raw * 10000;
                    $c15_raw = $c3_raw;
                    $c16_raw = $c15_raw * 1000;
                    $c17_raw = ($c15_raw * 28.01) / 24.45;

                    // ==========================
                    // POTONG DESIMAL (BUKAN ROUND)
                    // ==========================

                    $c3  = $this->cutDecimal($c3_raw, 4);
                    $c2  = $this->cutDecimal($c2_raw, 4);
                    $c1  = $this->cutDecimal($c1_raw, 4);
                    $c4  = $this->cutDecimal($c4_raw, 4);
                    $c5  = $this->cutDecimal($c5_raw, 4);
                    $c15 = $this->cutDecimal($c15_raw, 4);
                    $c16 = $this->cutDecimal($c16_raw, 4);
                    $c17 = $this->cutDecimal($c17_raw, 4);

                    $satuan = "ppm";

                    // ==========================
                    // CEK BATAS BAWAH (SETELAH HITUNG SELESAI)
                    // ==========================

                    $c1 = number_format($c1, 2, '.', '');
                    $c2 = number_format($c2, 5, '.', '');
                    $c3 = number_format($c3, 2, '.', '');
                    $c5 = number_format($c3, 2, '.', '');
                    $c15 = $c3;
                    $c16 = $c1;
                    $c17 = $c2;

                }
                
                else if (in_array($row->parameter, $paramVoc)) {
                    // $vocRata2 = $totalNilai / $jumlahElemen;

                    // $c2 = number_format($vocRata2 * ($suhu / $tekanan_udara) * (298 / 760), 3);
                    // $c1 = number_format($c2 * 1000, 3);
                    // $c17 = number_format($vocRata2, 3);
                    // $c16 = number_format($c17 * 1000, 3);
                    // $c3 = number_format(($c17 * 24.45) / 78.9516, 3);
                    // $c15 = $c3;
                    // $satuan = "mg/m3";

                    // if ($c2 < 0.001) $c2 = '<0.001'; else $c2 = number_format($c2, 3);
                    $vocRata2 = $totalNilai / $jumlahElemen;

                    // HITUNG MURNI
                    $c2_raw  = $vocRata2 * ($suhu / $tekanan_udara) * (298 / 760);
                    $c1_raw  = $c2_raw * 1000;
                    $c17_raw = $vocRata2;
                    $c16_raw = $c17_raw * 1000;
                    $c3_raw  = ($c17_raw * 24.45) / 78.9516;

                    $satuan = "mg/m3";

                    // FUNCTION POTONG DESIMAL (TANPA ROUND)
                    $cut = function ($value, $decimal = 3) {
                        $factor = pow(10, $decimal);
                        return floor($value * $factor) / $factor;
                    };

                    // FORMAT AKHIR
                    $c2  = number_format($cut($c2_raw), 3);
                    $c1  = number_format($cut($c1_raw), 3);
                    $c17 = number_format($cut($c17_raw), 3);
                    $c16 = number_format($cut($c16_raw), 3);
                    $c3  = number_format($cut($c3_raw), 3);
                    $c15 = $c3;

                }

                else if (in_array($row->parameter, $paramO2)) {
                    $c5 = $totalNilai / $jumlahElemen;
                    $satuan = "%";

                    // cek batas bawah di akhir
                    $c5 = number_format($c5, 2);
                }else if (in_array($row->parameter, $paramCO2)) {
                    $rata_rata = $totalNilai / $jumlahElemen;

                    // gunakan nilai numerik dulu
                    $c3  = $rata_rata;

                    $c2  = (($c3 * 44.01) / 24.45) * ($suhu / $tekanan_udara) * (298 / 760);
                    $c1  = $c2 * 1000;

                    $c4  = $c3 * 1000;
                    $c5  = $c3 / 10000;

                    $c15 = $c3;
                    $c17 = ($c15 * 44.01 / 24.45);
                    $c16 = $c17 * 1000;

                    // baru format untuk output
                    // $c1  = number_format($c1, 4, '.', '');
                    // $c2  = number_format($c2, 4, '.', '');
                    // $c3  = number_format($c3, 4, '.', '');
                    // $c4  = number_format($c4, 4, '.', '');
                    // $c5  = number_format($c5, 4, '.', '');
                    // $c15 = number_format($c15, 4, '.', '');
                    // $c16 = number_format($c16, 4, '.', '');
                    // $c17 = number_format($c17, 4, '.', '');

                    $satuan = "ppm";

                    $c1 = number_format($c1, 2, '.', '');
                    $c2 = number_format($c2, 1, '.', '');
                    $c3 = number_format($c3, 1, '.', '');
                    $c4 = number_format($c3, 2, '.', '');
                    $c5 = number_format($c3, 2, '.', '');
                    $c15 = $c3;
                    $c16 = $c1;
                    $c17 = $c2;
                }
            }
        }

        return [
            'c1' => $c1,
            'c2' => $c2,
            'c3' => $c3,
            'c4' => $c4,
            'c5' => $c5,
            'c15' => $c15,
            'c16' => $c16,
            'c17' => $c17,
            'satuan' => $satuan,
        ];
    }

    private function cutDecimal($value, $decimal)
    {
        $factor = pow(10, $decimal);
        return floor($value * $factor) / $factor;
    }
}