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
                    $c4 = $c3 * 1000;
                    $c5 = $c3 * 10000;
                    $satuan = "ppm";

                    $c1 = $c1 < 11.45 ? '<11.45' : round($c1, 2);
                    $c3 = $c3 < 0.01 ? '<0.01' : round($c3, 2);
                    $c2 = $c2 < 0.01145 ? '<0.01145' : round($c2, 5);

                    if($row->parameter == "C O"){
                        $c15 = $c3;
                        $c16 = $c1;
                        $c17 = $c2;
                    }
                } 
                else if (in_array($row->parameter, $h2co)) {
                    $c2 = $totalNilai / $jumlahElemen;
                    $c2 = $c2 < 1 ? '<1' : round($c2, 0);
                    $c1 = $c2 * 1000;
                    $c3 = ($c2 / 24.45) * 30.03;
                    $satuan = "mg/m3";
                } 
                else if (in_array($row->parameter, $paramVoc)) {
                    $c2 = $totalNilai / $jumlahElemen;
                    $c2 = $c2 < 0.001 ? '<0.001' : round($c2, 3);
                    $c1 = $c2 * 1000;
                    $satuan = "mg/m3";
                } 
                else if (in_array($row->parameter, $paramO2)) {
                    $c5 = round($totalNilai / $jumlahElemen, 2);
                    $satuan = "%";
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