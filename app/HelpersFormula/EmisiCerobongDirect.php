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
        $c1 = $c2 = $c3 = $c4 = $c5 = $c6 = $c7 = $c8 = $c9 = $c10 = $c11 = $c12 = NULL;

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
            $c6 = round($data->CO2, 2);
            $satuan = '%';
        } elseif (in_array($id_parameter, $paramO2)) {
            $c6 = round($data->O2, 2);
            $satuan = '%';
        } elseif (in_array($id_parameter, $paramOpasitas)) {
            // Ubah string JSON jadi array angka
            $values = json_decode($data->nilai_opasitas, true);

            if (is_array($values) && count($values) > 0) {
                // Hitung rata-rata
                $rataRata = array_sum($values) / count($values);

                // Jika hasil kurang dari 0.83, tampilkan "<0.83", kalau tidak tampilkan angka dibulatkan 1 desimal
                $c6 = round($rataRata, 2);
                $satuan = '%';
            } else {
                // Kalau datanya kosong
                $c6 = null;
                $satuan = null;
            }
        } elseif (in_array($id_parameter, $paramSuhu)) {
            $c7 = round($data->T_Flue, 2);
            $satuan = '°C';
        } elseif (in_array($id_parameter, $paramVelocity)) {
           // Ambil hanya angka setelah tanda ":" (bukan angka pada Data-1)
            preg_match_all('/:\s*(\d+(?:\.\d+)?)/', $data->velocity, $matches);
            // Ambil hanya group angka
            $angka = array_map('floatval', $matches[1]); // group 1 = angka setelah ':'
            if (count($angka) > 0) {
                $c10 = array_sum($angka) / count($angka);
                $c10 = number_format($c10, 4, '.', '');
                $satuan = 'm/s';
            } else {
                $c10 = null;
                $satuan = null;
            }

        } else if (in_array($id_parameter, $paramNO2)) {
            $c3 = $data->NO2;                       // raw
            $c2 = (($c3 * 46) / 24.45);             // raw
            $c1 = intval($c2 * 1000);
            $c4 = $c1;
            $c5 = $c2;

            $satuan = ($id_parameter == "NO-NO2") ? 'mg/Nm³' : 'ppm';

        } else if (in_array($id_parameter, $paramNOX)) {
            $c3 = $data->NOx;
            $c2 = (($c3 * 46) / 24.45);
            $c1 = intval($c2 * 1000);
            $c4 = $c1;
            $c5 = $c2;

            $satuan = 'mg/Nm³';

        } else if (in_array($id_parameter, $paramSO2)) {
            $c3 = $data->SO2;
            $c2 = (($c3 * 64.066) / 24.45);
            $c1 = intval($c2 * 1000);
            $c4 = $c1;
            $c5 = $c2;

            $satuan = 'ppm';

        } else if (in_array($id_parameter, $paramEffisiensiPembakaran)) {
            $co2 = $data->CO2;
            $co = $data->CO / 10000;
            $c6 = ($co2 / ($co2 + $co)) * 100;
            $satuan = '%';

        } else if (in_array($id_parameter, $paramNO)) {
            $c3 = $data->NO;
            $c2 = (($c3 * 30.01) / 24.45);
            $c1 = intval($c2 * 1000);
            $c4 = $c1;
            $c5 = $c2;

            $satuan = 'ppm';

        } else if (in_array($id_parameter, $paramCO)) {
            $c3 = $data->CO;
            $c2 = (($c3 * 28.01) / 24.45);
            $c1 = intval($c2 * 1000);
            $c4 = $c1;
            $c5 = $c2;

            $satuan = 'ppm';

        } else if (in_array($id_parameter, $paramSO2P)) {
            if ($avg_so2p !== null) {
                $c3 = $avg_so2p;
                $c2 = (($c3 * 64.066) / 24.45);
                $c1 = intval($c2 * 1000);
                $c4 = $c1;
                $c5 = $c2;

                $satuan = 'ppm';
            }

        } else if (in_array($id_parameter, $paramCOP)) {
            if ($avg_co_p !== null) {
                $c3 = $avg_co_p;
                $c2 = (($c3 * 28.01) / 24.45);
                $c1 = intval($c2 * 1000);
                $c4 = $c1;
                $c5 = $c2;

                $satuan = 'ppm';
            }

        } else if (in_array($id_parameter, $paramO2P)) {
            if ($avg_no2p !== null) {
                $c6 = $avg_no2p;
                $satuan = '%';
            }

        } else if (in_array($id_parameter, $paramNO2_NOxP)) {
            $c3 = $avg_nox_p;
            $c2 = (($c3 * 46) / 24.45);
            $c1 = intval($c2 * 1000);
            $c4 = $c1;
            $c5 = $c2;

            $satuan = 'mg/Nm³';
        }else if (in_array($id_parameter, $paramTekananUdara)) {
            $c12 = $data->tekanan_udara;
            $satuan = "mmHg";
        }

        // ======================
        // BLOK FORMATTING AKHIR
        // ======================

        // hanya jika value bukan string "<1" dsb
        if (is_numeric($c2 ?? null)) {
            $c2 = number_format($c2, 4, '.', ',');
        }
        if (is_numeric($c3 ?? null)) {
            $c3 = number_format($c3, 1, '.', ',');
        }
        if (is_numeric($c5 ?? null)) {
            $c5 = number_format($c5, 4, '.', ',');
        }
        if (is_numeric($c6 ?? null)) {
            $c6 = number_format($c6, 2, '.', ',');
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
            'C12' => $c12,
            'satuan' => $satuan
        ];
    }
}