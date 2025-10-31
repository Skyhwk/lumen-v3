<?php

namespace App\HelpersFormula;

class EmisiCerobongDirect {
    private function avgFromJson($json) {
        if (!$json) return null;

        $values = json_decode($json, true);
        if (!is_array($values)) return null;

        $numbers = array_map('floatval', array_values($values));
        return count($numbers) > 0 ? array_sum($numbers) / count($numbers) : null;
    }

    public function index($data, $id_parameter, $mdl){
        // set NULL
        $c1 = $c2 = $c3 = $c4 = $c5 = $c6 = $c7 = $c8 = $c9 = $c10 = $c11 = NULL;

        // Daftar parameter
        $paramCO2 = ["CO2", "CO2 (ESTB)"];
        $paramO2 = ["O2", "O2 (ESTB)"];
        $paramOpasitas = ["Opasitas", "Opasitas (ESTB)"];
        $paramSuhu = ["Suhu"];
        $paramVelocity = ["Velocity"];
        $paramNO2 = ["NO2","NOx"];
        $paramNO = ["NO"];
        $paramSO2 = ["SO2"];
        $paramCO = ["CO"];
        $paramEffisiensiPembakaran = ["Effisiensi Pembakaran","Eff. Pembakaran"];
        $paramSO2P = ["SO2 (P)"];
        $paramCOP = ["CO (P)"];
        $paramO2P = ["O2 (P)"];

        $pa = $data->tekanan_udara;
        $ta = $data->suhu;

        $so2_p = $data->so2_populasi;
        $no2_p = $data->no2_populasi;
        $o2_p = $data->o2_populasi;
        $co2_p = $data->co2_populasi;
        $velocity_p = $data->velocity_populasi;
        $suhu_cerobong_p = $data->t_flue_populasi;
        $nox_p = $data->nox_populasi;
        $no_p = $data->no_populasi;
        $co_p = $data->co_populasi;

        // gunakan fungsi untuk rata-rata
        $avg_so2p = $this->avgFromJson($so2_p);
        $avg_no2p = $this->avgFromJson($no2_p);
        $avg_o2p = $this->avgFromJson($o2_p);
        $avg_co2p = $this->avgFromJson($co2_p);
        $avg_velocity_p = $this->avgFromJson($velocity_p);
        $avg_suhu_cerobong_p = $this->avgFromJson($suhu_cerobong_p);
        $avg_nox_p = $this->avgFromJson($nox_p);
        $avg_no_p = $this->avgFromJson($no_p);
        $avg_co_p = $this->avgFromJson($co_p);

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
                $c10 = $c10 < 0.1 ? '<0.1' : round($c10, 1);
                $satuan = 'm/s';
            } else {
                $c10 = null; // atau 0 tergantung kebutuhan
                $satuan = null;
            }
        } else if (in_array($id_parameter, $paramNO2)) {
            $c5 = round($data->NO2, 1);
            $c4 = round(($c5 / 46) * 24.45, 1);
            $c3 = round($c4 * 1000, 1);
            $c2 = round($c4 * ($pa / $ta) * (298/760), 1);
            $c1 = round($c2 * 1000, 1);
            $c5 = $c5 < 1 ? '<1' : $c5;
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
        } else if(in_array($id_parameter, $paramNO)){
            $c5 = round($data->NO, 1);
            $c2 = round((($c5 / 30) * 24.45) * ($pa / $ta) * (298/760), 1);
            $c1 = round($c2 * 1000, 1);
            $c5 = $c5 < 0.1 ? '<0.1' : $c5;
            $satuan = 'ppm';
        } else if(in_array($id_parameter, $paramCO)){
            $c5 = round($data->CO, 1);
            $c4 = round(($c5 / 28.01) * 24.45, 1);
            $c3 = round($c4 * 1000, 1);
            $c2 = round($c4 * ($pa / $ta) * (298/760), 1);
            $c1 = round($c2 * 1000, 1);
            $c5 = $c5 < 0.02 ? '<0.02' : $c5;
            $satuan = 'ppm';
        } else if (in_array($id_parameter, $paramSO2P)) {
            
            if ($avg_so2p !== null) {
                $c5 = round($avg_so2p, 1);
                $c4 = round(($c5 / 64.066) * 24.45, 1);
                $c3 = round($c4 * 1000, 1);
                $c2 = round($c4 * ($pa / $ta) * (298/760), 1);
                $c1 = round($c2 * 1000, 1);
                $satuan = 'ppm';
            } else {
                $c1 = $c2 = $c3 = $c4 = $c5 = null;
                $satuan = null;
            }

        } else if (in_array($id_parameter, $paramCOP)) {

            if ($avg_cop !== null) {
                $c5 = round($avg_cop, 1);
                $c4 = round(($c5 / 28.01) * 24.45, 1);
                $c3 = round($c4 * 1000, 1);
                $c2 = round($c4 * ($pa / $ta) * (298/760), 1);
                $c1 = round($c2 * 1000, 1);
                $satuan = 'ppm';
            } else {
                $c1 = $c2 = $c3 = $c4 = $c5 = null;
                $satuan = null;
            }

        } else if (in_array($id_parameter, $paramO2P)) {

            if ($avg_no2p !== null) {
                $c6 = round($avg_no2p, 1);
                $satuan = '%';
            } else {
                $c6 = null;
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