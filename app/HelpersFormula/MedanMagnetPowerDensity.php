<?php

namespace App\HelpersFormula;

class MedanMagnetPowerDensity
{
    public function index($data, $id_parameter, $mdl)
    {

        $magnet_3 = json_decode($data->data_lapangan['magnet_3']);
        $magnet_30 = json_decode($data->data_lapangan['magnet_30']);
        $magnet_100 = json_decode($data->data_lapangan['magnet_100']);
        $listrik_3 = json_decode($data->data_lapangan['listrik_3']);
        $listrik_30 = json_decode($data->data_lapangan['listrik_30']);
        $listrik_100 = json_decode($data->data_lapangan['listrik_100']);

        $frekuensi_3 = json_decode($data->data_lapangan['frekuensi_3']);
        $frekuensi_30 = json_decode($data->data_lapangan['frekuensi_30']);
        $frekuensi_100 = json_decode($data->data_lapangan['frekuensi_100']);

        $totmagnet_3 = count(array_keys($magnet_3));
        $totmagnet_30 = count(array_keys($magnet_30));
        $totmagnet_100 = count(array_keys($magnet_100));
        $totlistrik_3 = count(array_keys($listrik_3));
        $totlistrik_30 = count(array_keys($listrik_30));
        $totlistrik_100 = count(array_keys($listrik_100));

        $nilmagnet_3 = 0;
        $nilmagnet_30 = 0;
        $nilmagnet_100 = 0;
        $nillistrik_3 = 0;
        $nillistrik_30 = 0;
        $nillistrik_100 = 0;

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


        $nilmagnet_3_ = $nilmagnet_3 / $totmagnet_3;
        $nilmagnet_30_ = $nilmagnet_30 / $totmagnet_30;
        $nilmagnet_100_ = $nilmagnet_100 / $totmagnet_100;

        // Menghitung total nilai magnet
        $totalNilaiMagnet = $nilmagnet_3_ + $nilmagnet_30_ + $nilmagnet_100_;

        // Menghitung jumlah elemen
        $jumlahNilai = 3; // Karena ada 3 nilai

        // Menghitung rata-rata Magnet
        $rataRataMagnet = number_format($totalNilaiMagnet / $jumlahNilai, 4);

        $nillistrik_3_ = $nillistrik_3 / $totlistrik_3;
        $nillistrik_30_ = $nillistrik_30 / $totlistrik_30;
        $nillistrik_100_ = $nillistrik_100 / $totlistrik_100;

        // Menjumlahkan hasil
        $total_nillistrik = $nillistrik_3_ + $nillistrik_30_ + $nillistrik_100_;

        // Menghitung rata-rata
        $rata_rata_nillistrik = number_format($total_nillistrik / 3, 4);


        // RATA_RATA FREKUENSI

        // Menjumlahkan hasil
        $total_nilfrekuensi = $frekuensi_3 + $frekuensi_30 + $frekuensi_100;
        // Menghitung rata-rata
        $rata_rata_nilfrekuensi = number_format($total_nilfrekuensi / 3, 4);

        // HASIL KESELURUHAN
        $MagnitTesla = $rataRataMagnet / 1000000;


        $medanMagnitAm = $MagnitTesla * 797700;
        // Menampilkan hasil

        // Menghitung Hasil Watt
        $HasilWatt = $rata_rata_nillistrik * $medanMagnitAm;

        // Pastikan hasil akhir tidak dibulatkan
        $hasilmWat = $HasilWatt / 10;

        $formattedHasilmWat = number_format($hasilmWat, 4);
        $formattedHasilWat = number_format($HasilWatt, 4);
        $formattedHasilmagnet = number_format($medanMagnitAm, 4);

        // $hasil = json_encode(["Magnet_3" => $nilmagnet_3_, "Magnet_30" => $nilmagnet_30_, "Magnet_100" =>$nilmagnet_100_, "Listrik_3" => $nillistrik_3_, "Listrik_30" => $nillistrik_30_, "Listrik_100" =>$nillistrik_100_]);
        return [
            "medan_magnet_am" => $formattedHasilmagnet, 
            "hasil_watt" => $formattedHasilWat, 
            "hasil_mwatt" => $formattedHasilmWat,
            "rata_magnet" => $rataRataMagnet,
            "rata_listrik" => $rata_rata_nillistrik,
            "rata_frekuensi" => $rata_rata_nilfrekuensi
        ];
    }
}