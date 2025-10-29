<?php

namespace App\HelpersFormula;

class EmisiCerobongDirect {
    public function index($data, $id_parameter, $mdl){

        // set NULL
        $c1 = $c2 = $c3 = $c4 = $c5 = $c6 = $c7 = $c8 = $c9 = $c10 = $c11 = NULL;

        // Daftar parameter
        $paramCO2 = ["CO2", "CO2 (ESTB)"];
        $paramO2 = ["O2", "O2 (ESTB)"];
        $paramOpasitas = ["Opasitas", "Opasitas (ESTB)"];
        $paramSuhu = ["Suhu"];
        $paramVelocity = ["Velocity"];

        $satuan = NULL;

        // Hanya proses kalau jumlah data valid
        if (in_array($id_parameter, $paramCO2)) {
            $c6 = $data->CO2 < 0.1 ? '<0.1' : round($data->CO2, 1);
            $satuan = '%';
        } elseif (in_array($id_parameter, $paramO2)) {
            $c6 = $data->O2 < 0.1 ? '<0.1' : round($data->O2, 1);
            $satuan = '%';
        } elseif (in_array($id_parameter, $paramOpasitas)) {
            // Ubah string JSON jadi array angka
            $values = json_decode($data->nilai_opasitas, true);

            if (is_array($values) && count($values) > 0) {
                // Hitung rata-rata
                $rataRata = array_sum($values) / count($values);

                // Jika hasil kurang dari 0.83, tampilkan "<0.83", kalau tidak tampilkan angka dibulatkan 1 desimal
                $c6 = $rataRata < 0.83 ? '<0.83' : round($rataRata, 1);
                $satuan = '%';
            } else {
                // Kalau datanya kosong
                $c6 = null;
                $satuan = null;
            }
        } elseif (in_array($id_parameter, $paramSuhu)) {
            $c7 = $data->T_Flue < 0.1 ? '<0.1' : round($data->T_Flue, 1);
            $satuan = '°C';
        } elseif (in_array($id_parameter, $paramVelocity)) {

            // Ambil semua angka desimal dari string velocity
            preg_match_all('/\d+(\.\d+)?/', $data->velocity, $matches);

            // Ambil hasil angka dalam array
            $angka = $matches[0];

            if (!empty($angka)) {
                // Hitung rata-rata
                $c10 = array_sum($angka) / count($angka);
                $satuan = 'm/s';
            } else {
                $c10 = null; // atau 0 tergantung kebutuhan
                $satuan = null;
            }
        }
        
        return [
            'C1' => $c1,
            'C2' => $c2,
            'C3' => $c3,
            'C4' => $c4,
            'C5' => $c5,
            'C6' => $c6,
            'C7' => $c7,
            'C8' => $c8,
            'C9' => $c9,
            'C10' => $c10,
            'C11' => $c11,
            'satuan' => $satuan
        ];
    }
}