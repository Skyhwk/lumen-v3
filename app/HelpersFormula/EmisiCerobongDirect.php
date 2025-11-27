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
        $paramNO2 = ["NO2", "NO-NO2"];
        $paramNOX = ["NOx", "NOx-NO2"];
        $paramNO = ["NO"];
        $paramSO2 = ["SO2"];
        $paramCO = ["CO", "C O"];
        $paramTekananUdara = ["Tekanan Udara"];
        $paramEffisiensiPembakaran = ["Effisiensi Pembakaran","Eff. Pembakaran"];
        $paramSO2P = ["SO2 (P)"];
        $paramCOP = ["CO (P)"];
        $paramO2P = ["O2 (P)"];
        $paramNO2_NOxP = ["NO2-Nox (P)"];

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
            $c6 = $data->CO2 < 0.1 ? '<0.1' : round($data->CO2, 2);
            $satuan = '%';
        } elseif (in_array($id_parameter, $paramO2)) {
            $c6 = $data->O2 < 0.1 ? '<0.1' : round($data->O2, 2);
            $satuan = '%';
        } elseif (in_array($id_parameter, $paramOpasitas)) {
            // Ubah string JSON jadi array angka
            $values = json_decode($data->nilai_opasitas, true);

            if (is_array($values) && count($values) > 0) {
                // Hitung rata-rata
                $rataRata = array_sum($values) / count($values);

                // Jika hasil kurang dari 0.83, tampilkan "<0.83", kalau tidak tampilkan angka dibulatkan 1 desimal
                $c6 = $rataRata < 0.83 ? '<0.83' : round($rataRata, 2);
                $satuan = '%';
            } else {
                // Kalau datanya kosong
                $c6 = null;
                $satuan = null;
            }
        } elseif (in_array($id_parameter, $paramSuhu)) {
            $c7 = $data->T_Flue < 0.1 ? '<0.1' : round($data->T_Flue, 2);
            $satuan = '°C';
        } elseif (in_array($id_parameter, $paramVelocity)) {
           // Ambil hanya angka setelah tanda ":" (bukan angka pada Data-1)
            preg_match_all('/:\s*(\d+(?:\.\d+)?)/', $data->velocity, $matches);
            // Ambil hanya group angka
            $angka = array_map('floatval', $matches[1]); // group 1 = angka setelah ':'
            if (count($angka) > 0) {
                $c10 = array_sum($angka) / count($angka);
                $c10 = $c10 < 0.1 ? '<0.1' : number_format($c10, 4, '.', '');
                $satuan = 'm/s';
            } else {
                $c10 = null;
                $satuan = null;
            }

        } else if (in_array($id_parameter, $paramNO2)) {
            $c3 = $data->NO2;
            $c2 = round((($c3 * 46) / 24.45), 4);
            $c1 = intval($c2 * 1000);     // paksa jadi integer tanpa pembulatan
            $c4 = $c1;
            $c5 = $c2;

            $c3 = $c3 < 1 ? '<1' : $c3;
            if($id_parameter == "NO-NO2"){
                $satuan = 'mg/Nm³';
            }else{
                $satuan = 'ppm';
            }
        } else if (in_array($id_parameter, $paramNOX)) {
            $c3 = $data->NOx;
            $c2 = round((($c3 * 46) / 24.45), 4);
            $c1 = intval($c2 * 1000);     // paksa jadi integer tanpa pembulatan;
            $c4 = $c1;
            $c5 = $c2;
            $c3 = $c3 < 1 ? '<1' : $c3;

            $satuan = 'mg/Nm³';
        } else if (in_array($id_parameter, $paramSO2)) {

            $c3 = round($data->SO2, 1);
            $c2 = round(($c3 * 64.066) / 24.45,1);
            $c1 = intval($c2 * 1000);     // paksa jadi integer tanpa pembulatan;
            $c4 = $c1;
            $c5 = $c2;

            $c3 = $c3 < 1 ? '<1' : $c3;
            $satuan = 'ppm';
        } else if(in_array($id_parameter, $paramEffisiensiPembakaran)){
            $co2 = $data->CO2;
            $co = $data->CO / 10000;
            $c6 = round(($co2 / ($co2 + $co)) * 100, 4);
            $satuan = '%';
        } else if(in_array($id_parameter, $paramNO)){
            $c3 = round($data->NO, 1);
            $c2 = round((($c3 * 30.01) / 24.45) ,1);
            $c1 = intval($c2 * 1000);     // paksa jadi integer tanpa pembulatan;
            $c4 = $c1;
            $c5 = $c2;
            $c3 = $c3 < 0.1 ? '<0.1' : $c3;
            $satuan = 'ppm';
        } else if(in_array($id_parameter, $paramCO)){
            $c3 = $data->CO; //ppm
            $c2 = round((($c3 * 28.01) / 24.45) ,4);
            $c1 = intval($c2 * 1000);     // paksa jadi integer tanpa pembulatan
            $c4 = $c1;
            $c5 = $c2;
            $c3 = $c3 < 0.1 ? '<0.1' : $c3;
            $satuan = 'ppm';
        } else if (in_array($id_parameter, $paramSO2P)) {
            
            if ($avg_so2p !== null) {
                $c3 = round($avg_so2p, 1);
                $c2 = round(($c3 * 64.066) / 24.45, 4);
                $c1 = intval($c2 * 1000);     // paksa jadi integer tanpa pembulatan;
                $c4 = $c1;
                $c5 = $c2;

                $c1 = $c1 < 1 ? '<1' : $c1;
                $satuan = 'ppm';
            } else {
                $c1 = $c2 = $c3 = $c4 = $c3 = null;
                $satuan = null;
            }

        } else if (in_array($id_parameter, $paramCOP)) {

            if ($avg_co_p !== null) {
                $c3 = round($avg_co_p, 1);
                $c2= round(($c3 * 28.01) / 24.45, 4);
                $c1 = intval($c2 * 1000);
                $c4 = $c1;
                $c5 = $c2;

                $c3 = $c3 < 0.02 ? '<0.02' : $c1;
                $satuan = 'ppm';
            } else {
                $c1 = $c2 = $c3 = $c4 = $c3 = null;
                $satuan = null;
            }

        } else if (in_array($id_parameter, $paramO2P)) {

            if ($avg_no2p !== null) {
                $c6 = round($avg_no2p, 2);
                $c6 = $c6 < 0.1 ? '<0.1' : $c6;
                $satuan = '%';
            } else {
                $c6 = null;
                $satuan = null;
            }
        } else if(in_array($id_parameter, $paramNO2_NOxP)){
            $c3 = $avg_nox_p;
            $c2 = round(($c3 * 46) / 24.45, 4);
            $c1 = intval($c2 * 1000);     // paksa jadi integer tanpa pembulatan;
            $c4 = $c1;
            $c5 = $c2;

            $c3 = $c3 < 1 ? '<1' : $c3;
            $satuan = 'mg/Nm³';
        }
        // else if (in_array($id_parameter, $paramTekananUdara)) {
            
        //     $satuan = "mmHg";
        // }

        
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