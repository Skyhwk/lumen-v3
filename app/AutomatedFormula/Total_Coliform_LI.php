<?php

namespace App\AutomatedFormula;

use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\Titrimetri;
use App\Models\WsValueAir;
use Carbon\Carbon;

class Total_Coliform_LI
{
    public function index($required_parameter, $parameter, $no_sampel, $tanggal_terima)
    {
        $check = Colorimetri::where('parameter', $parameter)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
        if (isset($check->id)) {
            return;
        }
        $all_value = [];
        $acuan = [];
        $hasil = 0;
        foreach ($required_parameter as $key => $value) {
            $bod = ["BOD", "BOD (B-23-NA)", "BOD (B-23)"];
            $tss = ["TSS", "TSS (APHA-D-23-NA)", "TSS (APHA-D-23)", "TSS (IKM-SP-NA)", "TSS (IKM-SP)"];
            $nh3 = ["NH3", "NH3-N", "NH3-N Bebas", "NH3-N (3-03-NA)", "NH3-N (3-03)", "NH3-N (30-25-NA)", "NH3-N (30-25)", "NH3-N (T)", "NH3-N (T-NA)"];
            if (in_array($value, $bod)) {
                $data = Titrimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
            } else if(in_array($value, $nh3)) {
                $data = Colorimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
            } else {
                $data = Gravimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
            }

            if ($data) {
                $result = WsValueAir::where('id_colorimetri', $data->id)->first();

                if (in_array($value, $bod)) {
                    $acuan['BOD'] = (object) [
                        'hasil' => $result->hasil,
                        'acuan' => 50,
                        'greater' => is_numeric($result->hasil) ? $result->hasil > 50 : false,
                        'turun_naik' => is_numeric($result->hasil) ? number_format((50 - $result->hasil) / 50, 2) : 100
                    ];
                } else if (in_array($value, $tss)) {
                    $acuan['TSS'] = (object) [
                        'hasil' => $result->hasil,
                        'acuan' => 200,
                        'greater' => is_numeric($result->hasil) ? $result->hasil > 200 : false,
                        'turun_naik' => is_numeric($result->hasil) ? number_format((200 - $result->hasil) / 200, 2) : 100
                    ];
                } else if (in_array($value, $nh3)) {
                    $acuan['NH3'] = (object) [
                        'hasil' => $result->hasil,
                        'acuan' => 5,
                        'greater' => is_numeric($result->hasil) ? $result->hasil > 5 : false,
                        'turun_naik' => is_numeric($result->hasil) ? number_format((5 - $result->hasil) / 5, 2) : 100
                    ];
                }

                if (strpos($result->hasil, '<') !== false) {
                    $all_value[] = 0;
                } else {
                    if (strpos($result->hasil, ',') !== false) {
                        $all_value[] = str_replace(',', '', $result->hasil);
                    } else {
                        $all_value[] = $result->hasil;
                    }
                }

                /**
                 * Berhenti ketika item kedua karena 3 parameter pertama
                 * itu sudah mewakili satu parameter wajib yang harus ada
                 * ketika akan menghitung parameter ini
                 * */
                if (count($all_value) == 0 && $key == 2) {
                    break;
                } else if (count($all_value) == 3) {
                    break;
                }
            }
        }
        if (count($all_value) == 3) {

            $average_turun_naik = array_sum(array_column($acuan, 'turun_naik')) / count($acuan);
            $max_greater = max(array_column($acuan, 'greater'));
            $acuanTotalColi = 1000;

            // =if(G7>0,E7 - (G7*E7), E7+(G7*E7))
            $temp_result = $average_turun_naik > 0 ? $acuanTotalColi - (($average_turun_naik / 100) * $acuanTotalColi) : $acuanTotalColi + (($average_turun_naik / 100) * $acuanTotalColi);

            $isGreater = $temp_result >= 1600 ? true : false;

            $closest = $this->searchClosestKey(abs($temp_result) / 10, $isGreater);

            $hasil = $closest['key'];
            if ($hasil < 1) {
                $hasil = '<1';
            }
            $split_note = str_split($closest['value']);

            $note = implode('-', $split_note);

            $insert                     = new Colorimetri();
            $insert->no_sampel          = $no_sampel;
            $insert->parameter          = $parameter;
            $insert->tanggal_terima     = $tanggal_terima;
            $insert->hp                 = $hasil;
            $insert->jenis_pengujian    = 'sample';
            $insert->note               = $note;
            $insert->template_stp       = 2;
            $insert->created_by         = 'SYSTEM';
            $insert->created_at         = Carbon::now();
            $insert->is_approved        = true;
            $insert->approved_by         = 'SYSTEM';
            $insert->approved_at         = Carbon::now();
            $insert->save();

            $ws_hasil                   = new WsValueAir();
            $ws_hasil->id_colorimetri   = $insert->id;
            $ws_hasil->no_sampel        = $no_sampel;
            $ws_hasil->hasil            = $hasil;
            $ws_hasil->save();

            return $hasil;
        }
    }

    private function searchClosestKey($temp_result, $isLoop = false){
        $table = $this->tableReversedMPN;
        $hasil = null;

        // ubah semua key jadi float agar bisa dibandingkan numerik
        $keys = array_map('floatval', array_keys($table));
        sort($keys); // urutkan dari kecil ke besar

        $closest_key = null;
        do {
            // cari nilai key terdekat (paling mendekati ke atas/bawah)
            $min_diff = PHP_FLOAT_MAX;

            foreach ($keys as $key) {
                $diff = abs($key - $temp_result);
                if ($diff < $min_diff) {
                    $min_diff = $diff;
                    $closest_key = $key;
                }
            }

            // jika ketemu (selisih kecil), langsung ambil hasilnya
            if ($closest_key !== null) {
                $hasil = (string) $table[(string)$closest_key];
                break;
            }

            // jika tidak ketemu dan loop diizinkan, bagi 10 lalu ulangi
            $temp_result = $temp_result / 10;

            // jika tidak loop, hentikan setelah 1 kali
            if (!$isLoop) {
                break;
            }
        } while ($temp_result > 0.000001); // batas aman agar tidak infinite loop

        // fallback jika hasil belum ditemukan
        if ($hasil === null) {
            $hasil = '000'; // set nol
        }

        return [
            'value' => $hasil,
            'key' => $closest_key
        ];
    }

    private $tableReversedMPN = [
        "1.8" => 000,
        "3.6" => 011,
        "3.7" => 020,
        "5.5" => 021,
        "5.6" => 030,
        "2" => 100,
        "4" => 101,
        "6" => 102,
        "4" => 110,
        "6.1" => 111,
        "8.1" => 112,
        "6.1" => 120,
        "8.2" => 121,
        "8.3" => 130,
        "10" => 131,
        "11" => 140,
        "4.5" => 200,
        "6.8" => 201,
        "9.1" => 202,
        "6.8" => 210,
        "9.2" => 211,
        "12" => 212,
        "8.3" => 220,
        "12" => 221,
        "14" => 222,
        "12" => 230,
        "14" => 231,
        "15" => 240,
        "7.8" => 300,
        "11" => 301,
        "13" => 302,
        "11" => 310,
        "14" => 311,
        "17" => 312,
        "14" => 320,
        "17" => 321,
        "20" => 322,
        "17" => 330,
        "21" => 331,
        "24" => 332,
        "21" => 340,
        "24" => 341,
        "25" => 350,
        "13" => 400,
        "17" => 401,
        "21" => 402,
        "25" => 403,
        "17" => 410,
        "21" => 411,
        "26" => 412,
        "31" => 413,
        "22" => 420,
        "26" => 421,
        "32" => 422,
        "38" => 423,
        "27" => 430,
        "33" => 431,
        "39" => 432,
        "34" => 440,
        "40" => 441,
        "47" => 442,
        "41" => 450,
        "48" => 451,
        "23" => 500,
        "31" => 501,
        "43" => 502,
        "58" => 503,
        "33" => 510,
        "46" => 511,
        "63" => 512,
        "84" => 513,
        "49" => 520,
        "70" => 521,
        "94" => 522,
        "120" => 523,
        "150" => 524,
        "79" => 530,
        "110" => 531,
        "140" => 532,
        "170" => 533,
        "210" => 534,
        "130" => 540,
        "170" => 541,
        "220" => 542,
        "280" => 543,
        "350" => 544,
        "430" => 545,
        "240" => 550,
        "350" => 551,
        "540" => 552,
        "920" => 553,
        "1600" => 554
    ];
}
