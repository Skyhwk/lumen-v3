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

        $tekanan_udara = !empty($pa) ? round(array_sum($pa) / count($pa), 1) : 0;
        $suhu = !empty($ta) ? round(array_sum($ta) / count($ta), 1) : 0;

        // Inisialisasi default
        $c1 = $c2 = $c3 = $c4 = $c5 = $c15 = $c16 = $c17 = NULL;
        $satuan = NULL;

        // Daftar parameter
        $paramO2  = ["O2"];
        $paramCO2 = ["CO2", "CO2 (24 Jam)", "CO2 (8 Jam)"];
        $paramVoc = ["VOC", "VOC (8 Jam)"];
        $paramCO  = ["C O", "CO (8 Jam)", "CO (6 Jam)", "CO (24 Jam)"];

        // Hanya proses kalau jumlah data valid
        if ($jumlahElemen > 0) {
            foreach ($data as $row) {
                if (in_array($row->parameter, $paramCO)) {
                    $c3 = round($totalNilai / $jumlahElemen, 6);
                    $c2 = (($c3 * 28.01) / 24.45) * ($suhu / $tekanan_udara) * (298 / 760);
                    $c1 = $c2 * 1000;
                    $c4 = round($c3 * 1000, 6);
                    $c5 = round($c3 * 10000, 6);
                    $c15 = round($c3,6);
                    $c16 = round($c15 * 1000, 6);
                    $c17 = round($c15 * 28.01 / 24.45, 6);
                    $satuan = "ppm";

                    // setelah semua hitung selesai, baru cek batas bawah
                    // if ($c1 < 11.45) $c1 = '<11.45'; else $c1 = round($c1, 2);
                    // if ($c3 < 0.01) $c3 = '<0.01'; else $c3 = round($c3, 2);
                    // if ($c2 < 0.01145) $c2 = '<0.01145'; else $c2 = round($c2, 5);
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

                else if (in_array($row->parameter, $paramCO2)) {
                    $c3 = round($totalNilai / $jumlahElemen, 6);
                    $c2 = (($c3 * 44.01) / 24.45) * ($suhu / $tekanan_udara) * (298 / 760);
                    $c1 = $c2 * 1000;
                    $c4 = round($c3 * 1000, 6);
                    $c5 = round($c3 / 10000, 6);
                    $c15 = $c3;
                    $c17 = round($c15 * 44.01 / 24.45, 6);
                    $c16 = round($c17 * 1000, 6);
                    $satuan = "ppm";
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
}