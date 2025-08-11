<?php
namespace App\HelpersFormula;
use Carbon\Carbon;
class IklimDingin
{
    public function index($data, $id_parameter, $mdl){
        $totalShifts = 0;
        $hasil = 0;
        $hasil_suhu_terpapar = [];
        $hasil_angin_terpapar = [];
        $total_waktu_paparan = 0; // Menampung jumlah total waktu paparan

        foreach ($data as $indx => $val) {
            if ($val->pengukuran != null) {
                // Decode JSON menjadi array asosiatif PHP
                // dd($val);
                $dataa = json_decode($val->pengukuran, true);
                $totData = count($dataa);
                $totHasilSuhu = 0;
                $totHasilAngin = 0;
                $waktuPaparan = $val->akumulasi_waktu_paparan; // Ambil waktu paparan dari setiap data
                foreach ($dataa as $idx => $vl) {
                    // dd($dataa);
                    $totHasilSuhu += $vl['suhu_kering'];
                    $totHasilAngin += $vl['kecepatan_angin'];
                }
                // array_push($hasil_rata_suhu, $totHasilSuhu / $totData);
                // array_push($hasil_rata_angin, $totHasilAngin / $totData);

                // Menghitung rata-rata suhu dan angin untuk data ini
                $rataSuhu = $totHasilSuhu / $totData;
                $rataAngin = $totHasilAngin / $totData;

                // Menampung hasil perhitungan rata-rata suhu dan angin dikali waktu paparan ke dalam array
                $hasil_suhu_terpapar[] = $rataSuhu * $waktuPaparan;
                $hasil_angin_terpapar[] = $rataAngin * $waktuPaparan;
                $total_waktu_paparan += $waktuPaparan; // Menambahkan waktu paparan ke total waktu paparan  
            }
        }

        // Setelah loop selesai, menghitung total suhu dan angin terpapar
        $total_suhu_terpapar = array_sum($hasil_suhu_terpapar);
        $total_angin_terpapar = array_sum($hasil_angin_terpapar);

        // Menghitung rata-rata suhu dan angin terpapar
        $rataSuhu = $total_suhu_terpapar / $total_waktu_paparan;
        $rataAngin = $total_angin_terpapar / $total_waktu_paparan;
        // dd($rataSuhu, $rataAngin, $total_waktu_paparan);


        // RATA-RATA SUHU KERING
        if ($rataSuhu > 4.4) {
            $rataSuhu = 10;
        } else if ($rataSuhu > -1.1 && $rataSuhu <= 4.4) {
            $rataSuhu = 4.4;
        } else if ($rataSuhu > -6.7 && $rataSuhu <= -1.1) {
            $rataSuhu = -1.1;
        } else if ($rataSuhu > -12.2 && $rataSuhu <= -6.7) {
            $rataSuhu = -6.7;
        } else if ($rataSuhu > -17.8 && $rataSuhu <= -12.2) {
            $rataSuhu = -12.2;
        } else if ($rataSuhu > -23.3 && $rataSuhu <= -17.8) {
            $rataSuhu = -17.8;
        } else if ($rataSuhu > -28.9 && $rataSuhu <= -23.3) {
            $rataSuhu = -23.3;
        } else if ($rataSuhu > -34.4 && $rataSuhu <= -28.9) {
            $rataSuhu = -28.9;
        } else if ($rataSuhu > -40.0 && $rataSuhu <= -34.4) {
            $rataSuhu = -34.4;
        } else if ($rataSuhu > -45.6 && $rataSuhu <= -40.0) {
            $rataSuhu = -40.0;
        } else if ($rataSuhu <= -45.6) {
            $rataSuhu = -45.6;
        }



        // Bulatkan kecepatan angin ke atas menjadi kelipatan 5
        $bulatKecepatanAngin = ceil($rataAngin / 5) * 5;
        // Batasi maksimal rata-rata kecepatan angin menjadi 40
        $rataRataKecepatanAngin = min(40, $bulatKecepatanAngin);

        // dd($rataSuhu, $rataRataKecepatanAngin);
        switch ($rataRataKecepatanAngin) {
            case 5:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = 8.9;
                        break;
                    case 4.4:
                        $hasil = 2.8;
                        break;
                    case -1.1:
                        $hasil = -2.8;
                        break;
                    case -6.7:
                        $hasil = -8.9;
                        break;
                    case -12.2:
                        $hasil = -14.4;
                        break;
                    case -17.8:
                        $hasil = -20.6;
                        break;
                    case -23.3:
                        $hasil = -26.1;
                        break;
                    case -28.9:
                        $hasil = -32.2;
                        break;
                    case -34.4:
                        $hasil = -37.8;
                        break;
                    case -40.0:
                        $hasil = -43.9;
                        break;
                    case -45.6:
                        $hasil = -49.4;
                        break;
                    case -51.1:
                        $hasil = -55.6;
                        break;
                }
                break;
            // KECEPATAN ANGIN 10
            case 10:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = 4.4;
                        break;
                    case 4.4:
                        $hasil = -2.2;
                        break;
                    case -1.1:
                        $hasil = -8.9;
                        break;
                    case -6.7:
                        $hasil = -15.6;
                        break;
                    case -12.2:
                        $hasil = -22.8;
                        break;
                    case -17.8:
                        $hasil = -31.1;
                        break;
                    case -23.3:
                        $hasil = -36.1;
                        break;
                    case -28.9:
                        $hasil = -43.3;
                        break;
                    case -34.4:
                        $hasil = -50.0;
                        break;
                    case -40.0:
                        $hasil = -56.7;
                        break;
                    case -45.6:
                        $hasil = -63.9;
                        break;
                    case -51.1:
                        $hasil = -70.6;
                        break;
                }
                break;
            // KECEPATAN ANGIN 15
            case 15:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = 2.2;
                        break;
                    case 4.4:
                        $hasil = -5.6;
                        break;
                    case -1.1:
                        $hasil = -12.8;
                        break;
                    case -6.7:
                        $hasil = -20.6;
                        break;
                    case -12.2:
                        $hasil = -27.8;
                        break;
                    case -17.8:
                        $hasil = -35.6;
                        break;
                    case -23.3:
                        $hasil = -42.8;
                        break;
                    case -28.9:
                        $hasil = -50.0;
                        break;
                    case -34.4:
                        $hasil = -57.8;
                        break;
                    case -40.0:
                        $hasil = -65.0;
                        break;
                    case -45.6:
                        $hasil = -72.8;
                        break;
                    case -51.1:
                        $hasil = -80.0;
                        break;
                }
                break;
            // KECEPATAN ANGIN 20
            case 20:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = 0.0;
                        break;
                    case 4.4:
                        $hasil = -7.8;
                        break;
                    case -1.1:
                        $hasil = -15.6;
                        break;
                    case -6.7:
                        $hasil = -23.3;
                        break;
                    case -12.2:
                        $hasil = -31.7;
                        break;
                    case -17.8:
                        $hasil = -39.4;
                        break;
                    case -23.3:
                        $hasil = -47.2;
                        break;
                    case -28.9:
                        $hasil = -55.0;
                        break;
                    case -34.4:
                        $hasil = -63.3;
                        break;
                    case -40.0:
                        $hasil = -71.1;
                        break;
                    case -45.6:
                        $hasil = -78.9;
                        break;
                    case -51.1:
                        $hasil = -80.0;
                        break;
                }
                break;
            case 25:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = -1.1;
                        break;
                    case 4.4:
                        $hasil = -8.9;
                        break;
                    case -1.1:
                        $hasil = -17.8;
                        break;
                    case -6.7:
                        $hasil = -26.1;
                        break;
                    case -12.2:
                        $hasil = -33.9;
                        break;
                    case -17.8:
                        $hasil = -42.2;
                        break;
                    case -23.3:
                        $hasil = -50.6;
                        break;
                    case -28.9:
                        $hasil = -58.9;
                        break;
                    case -34.4:
                        $hasil = -66.7;
                        break;
                    case -40.0:
                        $hasil = -75.6;
                        break;
                    case -45.6:
                        $hasil = -83.3;
                        break;
                    case -51.1:
                        $hasil = -91.7;
                        break;
                }
                break;
            case 30:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = -2.2;
                        break;
                    case 4.4:
                        $hasil = -10.6;
                        break;
                    case -1.1:
                        $hasil = -18.9;
                        break;
                    case -6.7:
                        $hasil = -27.8;
                        break;
                    case -12.2:
                        $hasil = -36.1;
                        break;
                    case -17.8:
                        $hasil = -44.4;
                        break;
                    case -23.3:
                        $hasil = -52.8;
                        break;
                    case -28.9:
                        $hasil = -61.7;
                        break;
                    case -34.4:
                        $hasil = -70.0;
                        break;
                    case -40.0:
                        $hasil = -78.3;
                        break;
                    case -45.6:
                        $hasil = -87.2;
                        break;
                    case -51.1:
                        $hasil = -95.6;
                        break;
                }
                break;
            case 35:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = -2.8;
                        break;
                    case 4.4:
                        $hasil = -11.7;
                        break;
                    case -1.1:
                        $hasil = -20.0;
                        break;
                    case -6.7:
                        $hasil = -28.9;
                        break;
                    case -12.2:
                        $hasil = -37.2;
                        break;
                    case -17.8:
                        $hasil = -46.1;
                        break;
                    case -23.3:
                        $hasil = -55.0;
                        break;
                    case -28.9:
                        $hasil = -63.3;
                        break;
                    case -34.4:
                        $hasil = -72.2;
                        break;
                    case -40.0:
                        $hasil = -80.6;
                        break;
                    case -45.6:
                        $hasil = -89.4;
                        break;
                    case -51.1:
                        $hasil = -98.3;
                        break;
                }
                break;
            case 40:
                switch ($rataSuhu) {
                    case 10:
                        $hasil = -3.3;
                        break;
                    case 4.4:
                        $hasil = -12.2;
                        break;
                    case -1.1:
                        $hasil = -21.2;
                        break;
                    case -6.7:
                        $hasil = -21.1;
                        break;
                    case -12.2:
                        $hasil = -38.3;
                        break;
                    case -17.8:
                        $hasil = -47.2;
                        break;
                    case -23.3:
                        $hasil = -56.1;
                        break;
                    case -28.9:
                        $hasil = -65.0;
                        break;
                    case -34.4:
                        $hasil = -73.3;
                        break;
                    case -40.0:
                        $hasil = -82.2;
                        break;
                    case -45.6:
                        $hasil = -91.1;
                        break;
                    case -51.1:
                        $hasil = -100.0;
                        break;
                }
                break;
            default:
                $hasil = 'Tidak ada data';

        }

        return [
            'hasil' => $hasil,
            'rataSuhu' => $rataSuhu,
            'rataAngin' => $rataRataKecepatanAngin
        ];
    }
}