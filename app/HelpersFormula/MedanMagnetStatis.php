<?php

namespace App\HelpersFormula;

class MedanMagnetStatis
{
    public function index($data, $id_parameter, $mdl)
    {
        $magnet_3 = json_decode($data->data_lapangan['magnet_3']);
        $magnet_30 = json_decode($data->data_lapangan['magnet_30']);
        $magnet_100 = json_decode($data->data_lapangan['magnet_100']);
        
        $totmagnet_3 = count(array_keys($magnet_3));
        $totmagnet_30 = count(array_keys($magnet_30));
        $totmagnet_100 = count(array_keys($magnet_100));

        $frekuensi_3 = json_decode($data->data_lapangan['frekuensi_3']);
        $frekuensi_30 = json_decode($data->data_lapangan['frekuensi_30']);
        $frekuensi_100 = json_decode($data->data_lapangan['frekuensi_100']);
        
        $nilmagnet_3 = 0;
        $nilmagnet_30 = 0;
        $nilmagnet_100 = 0;

        foreach ($magnet_3 as $idx => $val) {
            foreach ($val as $k => $v) {
                $nilmagnet_3 += $v;
            }
        }
        foreach ($magnet_30 as $idx => $val) {
            foreach ($val as $k => $v) {
                $nilmagnet_30 += $v;
            }
        }
        foreach ($magnet_100 as $idx => $val) {
            foreach ($val as $k => $v) {
                $nilmagnet_100 += $v;
            }
        }

        // Menjumlahkan hasil
        $total_nilfrekuensi = $frekuensi_3 + $frekuensi_30 + $frekuensi_100;
        // Menghitung rata-rata
        $rata_rata_nilfrekuensi = number_format($total_nilfrekuensi / 3, 4, '.', '');

        $nilmagnet_3_ = number_format($nilmagnet_3 / $totmagnet_3, 4);
        $nilmagnet_30_ = number_format($nilmagnet_30 / $totmagnet_30, 4);
        $nilmagnet_100_ = number_format($nilmagnet_100 / $totmagnet_100, 4);

        // Menghitung total nilai magnet
        $totalNilaiMagnet = $nilmagnet_3_ + $nilmagnet_30_ + $nilmagnet_100_;

        // Menghitung jumlah elemen
        $jumlahNilai = 3; // Karena ada 3 nilai

        // Menghitung rata-rata
        $rataRataMagnet = $totalNilaiMagnet / $jumlahNilai;

        $rataRataMagnet = number_format($rataRataMagnet, 4);

        // $hasil = json_encode(["Magnet_3" => $nilmagnet_3_, "Magnet_30" => $nilmagnet_30_, "Magnet_100" =>$nilmagnet_100_]);
        return [
            'medan_magnet' => $rataRataMagnet,
            'rata_frekuensi' => $rata_rata_nilfrekuensi,
            // 'satuan' => 'V/m'
        ];
    }
}