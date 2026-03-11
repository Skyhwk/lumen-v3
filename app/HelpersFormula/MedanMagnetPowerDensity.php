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
        $nab_power_density = null; 
        $nab_medan_magnet = null; 
        $nab_medan_listrik = null; 

        $waktu_pemaparan = $data->data_lapangan['waktu_pemaparan'];

        
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
        $rataRataMagnet = number_format($totalNilaiMagnet / $jumlahNilai, 4, '.', '');

        $nillistrik_3_ = $nillistrik_3 / $totlistrik_3;
        $nillistrik_30_ = $nillistrik_30 / $totlistrik_30;
        $nillistrik_100_ = $nillistrik_100 / $totlistrik_100;

        // Menjumlahkan hasil
        $total_nillistrik = $nillistrik_3_ + $nillistrik_30_ + $nillistrik_100_;

        // Menghitung rata-rata
        $rata_rata_nillistrik = number_format($total_nillistrik / 3, 4, '.', '');

        // RATA_RATA FREKUENSI

        // Menjumlahkan hasil
        $total_nilfrekuensi = $frekuensi_3 + $frekuensi_30 + $frekuensi_100;
        // Menghitung rata-rata
        $rata_rata_nilfrekuensi = number_format($total_nilfrekuensi / 3, 4, '.', '');

        // HASIL KESELURUHAN
        $MagnitTesla = $rataRataMagnet / 1000000;

        $medanMagnitAm = $MagnitTesla * 797700;
        // Menampilkan hasil

        // Menghitung Hasil Watt
        $HasilWatt = $rata_rata_nillistrik * $medanMagnitAm;

        // Pastikan hasil akhir tidak dibulatkan
        $hasilmWat = $HasilWatt / 10;

        $formattedHasilmWat = number_format($hasilmWat, 4 , '.', '');
        $formattedHasilWat = number_format($HasilWatt, 4 , '.', '');
        $formattedHasilmagnet = number_format($medanMagnitAm, 4 , '.', '');

        $Hasilnab = [];
        if($waktu_pemaparan <= 6){
            $Hasilnab = $this->hitungNAB($rata_rata_nilfrekuensi);
        }

        // $hasil = json_encode(["Magnet_3" => $nilmagnet_3_, "Magnet_30" => $nilmagnet_30_, "Magnet_100" =>$nilmagnet_100_, "Listrik_3" => $nillistrik_3_, "Listrik_30" => $nillistrik_30_, "Listrik_100" =>$nillistrik_100_]);
        return [
            'hasilWs' =>[
                "medan_magnet_am" => $formattedHasilmagnet, 
                "hasil_watt" => $formattedHasilWat, 
                "hasil_mwatt" => $formattedHasilmWat,
                "rata_magnet" => $rataRataMagnet,
                "rata_listrik" => $rata_rata_nillistrik,
                "rata_frekuensi" => $rata_rata_nilfrekuensi,
            ],
            'nab' => [
                "nab_power_density" => $Hasilnab["nab_power_density"] ?? null,
                "nab_medan_magnet"  => $Hasilnab["nab_medan_magnet"] ?? null,
                "nab_medan_listrik" => $Hasilnab["nab_medan_listrik"] ?? null,
            ]
        ];
    }

    private function hitungNAB($hz)
    {
        $tabel = [
            [
                "min" => 30000, "max" => 100000,
                "label" => "30 kHz - 100 kHz",
                "power_density" => null,
                "medan_listrik" => 1842,
                "medan_magnet"  => 163
            ],
            [
                "min" => 100000, "max" => 1000000,
                "label" => "100 kHz - 1 MHz",
                "power_density" => null,
                "medan_listrik" => 1842,
                "medan_magnet"  => '16.3/f'
            ],
            [
                "min" => 1000000, "max" => 30000000,
                "label" => "1 MHz - 30 MHz",
                "power_density" => null,
                "medan_listrik" => "1842/f",
                "medan_magnet"  => "16.3/f"
            ],
            [
                "min" => 30000000, "max" => 100000000,
                "label" => "30 MHz - 100 MHz",
                "power_density" => null,
                "medan_listrik" => 61.4,
                "medan_magnet"  => "16.3/f"
            ],
            [
                "min" => 100000000, "max" => 300000000,
                "label" => "100 MHz - 300 MHz",
                "power_density" => 10,
                "medan_listrik" => 61.4,
                "medan_magnet"  => 0.163
            ],
            [
                "min" => 300000000, "max" => 3000000000,
                "label" => "300 MHz - 3 GHz",
                "power_density" => "f/30",
                "medan_listrik" => null,
                "medan_magnet"  => null
            ],
            [
                "min" => 3000000000, "max" => 30000000000,
                "label" => "3 GHz - 30 GHz",
                "power_density" => 100,
                "medan_listrik" => null,
                "medan_magnet"  => null
            ]
        ];

        foreach ($tabel as $row) {
            if ($hz >= $row["min"] && $hz <= $row["max"]) {

                // siapkan output
                $output = [
                    "kategori" => $row["label"],
                    "nab_power_density" => null,
                    "nab_medan_listrik" => null,
                    "nab_medan_magnet" => null,
                    "waktu_pemaparan" => $row["waktu"]
                ];

                $frekuensi_hz = $hz ; // convert Hz → MHz

                // POWER DENSITY
                if (is_string($row["power_density"])) {

                    if ($row["power_density"] === "f/30") {
                        $output["nab_power_density"] = $frekuensi_hz / 30;
                    }
                } else {
                    $output["nab_power_density"] = $row["power_density"];
                }

                // MEDAN LISTRIK
                if (is_string($row["medan_listrik"])) {
                    if ($row["medan_listrik"] === "1842/f") {
                        $output["nab_medan_listrik"] = 1842 / $frekuensi_hz;
                    }
                } else {
                    $output["nab_medan_listrik"] = $row["medan_listrik"];
                }

                // MEDAN MAGNET
                if (is_string($row["medan_magnet"])) {
                    if ($row["medan_magnet"] === "16.3/f") {
                        $output["nab_medan_magnet"] = 16.3 / $frekuensi_hz;
                    }
                } else {
                    $output["nab_medan_magnet"] = $row["medan_magnet"];
                }

                return $output;
            }
        }

        // jika tidak masuk range manapun → null semua
        return [
            "kategori" => null,
            "nab_power_density" => null,
            "nab_medan_listrik" => null,
            "nab_medan_magnet" => null
        ];
    }

}