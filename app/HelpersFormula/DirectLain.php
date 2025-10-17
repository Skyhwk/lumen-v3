<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLain {
    public function index($data, $id_parameter, $mdl) {
        $totalNilai = 0;
        $jumlahElemen = 0;

        foreach ($data as $dataItem) {
            $pengukuran = json_decode($dataItem->pengukuran, true);
            foreach ($pengukuran as $value) {
                $totalNilai += floatval($value);
                $jumlahElemen++;
            }
        }

        // Inisialisasi default
        $c1 = $c2 = $c3 = $c4 = $c5 = $c15 = $c16 = $c17 = NULL;
        $satuan = NULL;

        // Daftar parameter
        $paramO2  = ["O2"];
        $paramCO2 = ["CO2"];
        $paramVoc = ["VOC", "VOC (8 Jam)"];
        $paramCO  = ["C O", "CO (8 Jam)", "CO (6 Jam)", "CO (24 Jam)"];
        $h2co     = ["H2CO", "H₂CO"];

        // Hanya proses kalau jumlah data valid
        if ($jumlahElemen > 0) {
            foreach ($data as $row) {
                if (in_array($row->parameter, $paramCO)) {
                    $c3 = $totalNilai / $jumlahElemen;
                    $c2 = 0.0409 * $c3 * 28.01;
                    $c1 = $c2 * 1000;
                    $c4 = round($c3 * 1000, 6);
                    $c5 = round($c3 * 10000, 6);
                    $satuan = "ppm";

                    // simpan hasil murni dulu
                    if ($row->parameter == "C O") {
                        $c15 = round($c3,2);
                        $c16 = round($c1,2);
                        $c17 = round($c2,5);
                    }

                    // setelah semua hitung selesai, baru cek batas bawah
                    if ($c1 < 11.45) $c1 = '<11.45'; else $c1 = round($c1, 2);
                    if ($c3 < 0.01) $c3 = '<0.01'; else $c3 = round($c3, 2);
                    if ($c2 < 0.01145) $c2 = '<0.01145'; else $c2 = round($c2, 5);
                }

                else if (in_array($row->parameter, $h2co)) {
                    $c2 = round($totalNilai / $jumlahElemen, 6);
                    $c1 = round($c2 * 1000, 6);
                    $c3 = round(($c2 / 24.45) * 30.03, 6);
                    $satuan = "mg/m3";

                    // cek batas bawah di akhir
                    if ($c2 < 1) $c2 = '<1'; else $c2 = round($c2, 6);
                }

                else if (in_array($row->parameter, $paramVoc)) {
                    $c2 = $totalNilai / $jumlahElemen;
                    $c1 = round($c2 * 1000, 6);
                    $satuan = "mg/m3";

                    // cek batas bawah di akhir
                    if ($c2 < 0.001) $c2 = '<0.001'; else $c2 = round($c2, 3);
                }

                else if (in_array($row->parameter, $paramO2)) {
                    $c5 = $totalNilai / $jumlahElemen;
                    $satuan = "%";

                    // cek batas bawah di akhir
                    $c5 = round($c5, 2);
                }

                // else if (in_array($row->parameter, $paramCO2)) {
                //     $c3 = round($totalNilai / $jumlahElemen, 2);
                //     $satuan = "%";
                // }
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
}