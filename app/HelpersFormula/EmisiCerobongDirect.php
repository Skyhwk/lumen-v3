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
        $paramNO2 = ["NO2","NOx","NO"];
        $paramSO2 = ["SO2"];
        $paramEffisiensiPembakaran = ["Effisiensi Pembakaran","Eff. Pembakaran"];

        $pa = $data->tekanan_udara;
        $ta = $data->suhu;


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
        else if (in_array($id_parameter, $paramNO2)) {
            $c5 = round($data->NO2, 1);
            if($id_parameter != "NO"){
                $c4 = round(($c5 / 46) * 24.45, 1);
                $c3 = round($c4 * 1000, 1);
            }
            $c2 = round($c4 * ($pa / $ta) * (298/760), 1);
            $c1 = round($c2 * 1000, 1);

            $c5 = $c5 < 1 ? '<1' : $c5;
            if($id_parameter == "NO"){
                $c5 = $c5 < 0.1 ? '<0.1' : $c5;
            }
            $satuan = 'ppm';
        } else if (in_array($id_parameter, $paramSO2)) {
            $pa = $data->tekanan_udara;
            $ta = $data->suhu;
            $c5 = round($data->SO2, 1);
            $c4 = round(($c5 / 64.066) * 24.45, 1);
            $c3 = round($c4 * 1000, 1);
            $c2 = round($c4 * ($pa / $ta) * (298/760), 1);
            $c1 = round($c2 * 1000, 1);

            $c5 = $c5 < 1 ? '<1' : $c5;
            $satuan = 'ppm';
        } else if(in_array($id_parameter, $paramEffisiensiPembakaran)){
            $co2 = $data->CO2;
            $nCO2 = round(($co2 * 10000 * 44 * 1000) / 21500, 1);
            $c6 = ($nCO2 / ($nCO2 + $data->CO)) * 100/100;
            $satuan = '%';
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