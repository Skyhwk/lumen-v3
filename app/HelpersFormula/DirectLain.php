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
    $c1 = $c2 = $c3 = $c4 = $c5 = NULL;
    $satuan = NULL;

    // Daftar parameter
    $paramO2  = ["O2"];
    $paramCO2 = ["CO2"];
    $paramVoc = ["VOC", "VOC (8 Jam)"];
    $paramCO  = ["C O", "CO (8 Jam)", "CO (6 Jam)", "CO (24 Jam)"];
    $h2co     = ["H2CO", "HCHO (8 Jam)"];

    // Hanya proses kalau jumlah data valid
    if ($jumlahElemen > 0) {
        if (in_array($data->parameter, $paramCO)) {
            $c3 = $totalNilai / $jumlahElemen;
            $c2 = 0.0409 * $c3 * 28.01;
            $c1 = $c2 * 1000;
            $c4 = $c3 * 1000;
            $c5 = $c3 * 10000;
            $satuan = "ppm";

            $c1 = $c1 < 11.45 ? '<11.45' : round($c1, 2);
            $c3 = $c3 < 0.01 ? '<0.01' : round($c3, 2);
            $c2 = $c2 < 0.01145 ? '<0.01145' : round($c2, 5);
        } 
        else if (in_array($data->parameter, $h2co)) {
            $c2 = $totalNilai / $jumlahElemen;
            $c2 = $c2 < 1 ? '<1' : round($c2, 0);
            $c1 = $c2 * 1000;
            $c3 = ($c2 / 24.45) * 30.03;
            $satuan = "mg/Nm3";
        } 
        else if (in_array($data->parameter, $paramVoc)) {
            $c2 = $totalNilai / $jumlahElemen;
            $c2 = $c2 < 0.001 ? '<0.001' : round($c2, 3);
            $c1 = $c2 * 1000;
            $satuan = "mg/Nm3";
        } 
        else if (in_array($data->parameter, $paramO2)) {
            $c5 = round($totalNilai / $jumlahElemen, 2);
            $satuan = "%";
        } 
        // else if (in_array($data->parameter, $paramCO2)) {
        //     // contoh jika nanti kamu ingin tambahkan logika CO2, bisa disini
        //     $c3 = round($totalNilai / $jumlahElemen, 2);
        //     $satuan = "%";
        // }
    }

    return [
        'c1' => $c1,
        'c2' => $c2,
        'c3' => $c3,
        'c4' => $c4,
        'c5' => $c5,
        'satuan' => $satuan,
    ];
}

}