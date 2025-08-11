<?php

namespace App\HelpersFormula;

class MedanMagnetListrik
{
    public function index($data, $id_parameter, $mdl)
    {
        $listrik_3 = json_decode($data->data_lapangan['listrik_3']);
        $listrik_30 = json_decode($data->data_lapangan['listrik_30']);
        $listrik_100 = json_decode($data->data_lapangan['listrik_100']);
        $totlistrik_3 = count(array_keys($listrik_3));
        $totlistrik_30 = count(array_keys($listrik_30));
        $totlistrik_100 = count(array_keys($listrik_100));

        $nillistrik_3 = 0;
        $nillistrik_30 = 0;
        $nillistrik_100 = 0;

        foreach ($listrik_3 as $idx => $val) {
            foreach ($val as $k => $v) {
                $nillistrik_3 += $v;
            }
        }
        foreach ($listrik_30 as $idx => $val) {
            foreach ($val as $k => $v) {
                $nillistrik_30 += $v;
            }
        }
        foreach ($listrik_100 as $idx => $val) {
            foreach ($val as $k => $v) {
                $nillistrik_100 += $v;
            }
        }

        $nillistrik_3_ = number_format($nillistrik_3 / $totlistrik_3, 4);
        $nillistrik_30_ = number_format($nillistrik_30 / $totlistrik_30, 4);
        $nillistrik_100_ = number_format($nillistrik_100 / $totlistrik_100, 4);

        // Menjumlahkan hasil
        $total_nillistrik = $nillistrik_3_ + $nillistrik_30_ + $nillistrik_100_;
        // Menghitung rata-rata
        $rata_rata_nillistrik = $total_nillistrik / 3;
        // Format rata-rata
        $rata_rata_nillistrik = $rata_rata_nillistrik < 1 ? "<1" : number_format($rata_rata_nillistrik, 2);

        // $hasil = json_encode(["Listrik_3" => $nillistrik_3_, "Listrik_30" => $nillistrik_30_, "Listrik_100" =>$nillistrik_100_]);
        return [
            'medan_listrik' => $rata_rata_nillistrik,
            // 'satuan' => 'V/m'
        ];
    }
}