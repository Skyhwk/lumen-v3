<?php

namespace App\AutomatedFormula;

use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\Titrimetri;
use App\Models\WsValueAir;
use Carbon\Carbon;

class Total_Coliform_LD
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
            $tipe_data = '';
            if (in_array($value, $bod)) {
                $data = Titrimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
                $tipe_data = 'titrimetri';
            } else if (in_array($value, $nh3)) {
                $data = Colorimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
                $tipe_data = 'colorimetri';
            } else if (in_array($value, $tss)) {
                $data = Gravimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
                $tipe_data = 'gravimetri';
            }

            if ($data) {
                $result = WsValueAir::where('id_' . $tipe_data, $data->id)->first();

                if (in_array($value, $bod)) {
                    $acuan['BOD'] = (object) [
                        'hasil' => $result->hasil,
                        'acuan' => 30,
                        'greater' => is_numeric($result->hasil) ? $result->hasil > 30 : false,
                        'turun_naik' => is_numeric($result->hasil) ? number_format(((30 - $result->hasil) / 30) * 100, 2) : 100
                    ];
                } else if (in_array($value, $tss)) {
                    $acuan['TSS'] = (object) [
                        'hasil' => $result->hasil,
                        'acuan' => 30,
                        'greater' => is_numeric($result->hasil) ? $result->hasil > 30 : false,
                        'turun_naik' => is_numeric($result->hasil) ? number_format(((30 - $result->hasil) / 30) * 100, 2) : 100
                    ];
                } else if (in_array($value, $nh3)) {
                    $acuan['NH3'] = (object) [
                        'hasil' => $result->hasil,
                        'acuan' => 10,
                        'greater' => is_numeric($result->hasil) ? $result->hasil > 10 : false,
                        'turun_naik' => is_numeric($result->hasil) ? number_format(((10 - $result->hasil) / 10) * 100, 2) : 100
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
            // dd($all_value);
            $average_turun_naik = number_format((array_sum(array_column($acuan, 'turun_naik')) / count($acuan)) + 25, 2); // G7
            $max_greater = max(array_column($acuan, 'greater'));
            $acuanTotalColi = 3000; // E7

            // =if(G7>0,E7 - (G7*E7), E7+(G7*E7))
            $temp_result = $average_turun_naik > 0 ? $acuanTotalColi - (abs(($average_turun_naik / 100) * $acuanTotalColi)) : $acuanTotalColi + (abs(($average_turun_naik / 100) * $acuanTotalColi));

            $temp_result = $this->mround($temp_result, 10);

            $isGreater = $temp_result >= 1600 ? true : false;

            $closest = $this->searchClosestKey(abs($temp_result) / 10, $isGreater);

            $hasil = $closest['key'];
            if ($hasil < 1) {
                $hasil = '<1';
            }
            $split_note = str_split((string) $closest['value']);

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

    private function searchClosestKey($temp_result, $isLoop = false)
    {
        $rows = $this->tableReversedMPN;
        $closest = null;
        $closestDiff = PHP_FLOAT_MAX;

        do {
            foreach ($rows as $r) {
                $diff = abs($r["key"] - $temp_result);

                if ($diff < $closestDiff) {
                    $closestDiff = $diff;
                    $closest = $r;
                }
            }

            if ($closest !== null) {
                return [
                    "value" => $closest["value"],
                    "key"   => $closest["key"]
                ];
            }

            $temp_result /= 10;

            if (!$isLoop) {
                break;
            }
        } while ($temp_result > 0.000001);

        return [
            "value" => "000",
            "key"   => null
        ];
    }

    private function mround($number, $multiple)
    {
        return round($number / $multiple) * $multiple;
    }

    private $tableReversedMPN = [
        ["key" => 1.8, "value" => "001"],
        ["key" => 3.6, "value" => "011"],
        ["key" => 3.7, "value" => "020"],
        ["key" => 5.5, "value" => "021"],
        ["key" => 5.6, "value" => "030"],
        ["key" => 2, "value" => "100"],
        ["key" => 4, "value" => "101"],
        ["key" => 6, "value" => "102"],
        ["key" => 4, "value" => "110"],
        ["key" => 6.1, "value" => "111"],
        ["key" => 8.1, "value" => "112"],
        ["key" => 6.1, "value" => "120"],
        ["key" => 8.2, "value" => "121"],
        ["key" => 8.3, "value" => "130"],
        ["key" => 10, "value" => "131"],
        ["key" => 11, "value" => "140"],
        ["key" => 4.5, "value" => "200"],
        ["key" => 6.8, "value" => "201"],
        ["key" => 9.1, "value" => "202"],
        ["key" => 6.8, "value" => "210"],
        ["key" => 9.2, "value" => "211"],
        ["key" => 12, "value" => "212"],
        ["key" => 8.3, "value" => "220"],
        ["key" => 12, "value" => "221"],
        ["key" => 14, "value" => "222"],
        ["key" => 12, "value" => "230"],
        ["key" => 14, "value" => "231"],
        ["key" => 15, "value" => "240"],
        ["key" => 7.8, "value" => "300"],
        ["key" => 11, "value" => "301"],
        ["key" => 13, "value" => "302"],
        ["key" => 11, "value" => "310"],
        ["key" => 14, "value" => "311"],
        ["key" => 17, "value" => "312"],
        ["key" => 14, "value" => "320"],
        ["key" => 17, "value" => "321"],
        ["key" => 20, "value" => "322"],
        ["key" => 17, "value" => "330"],
        ["key" => 21, "value" => "331"],
        ["key" => 24, "value" => "332"],
        ["key" => 21, "value" => "340"],
        ["key" => 24, "value" => "341"],
        ["key" => 25, "value" => "350"],
        ["key" => 13, "value" => "400"],
        ["key" => 17, "value" => "401"],
        ["key" => 21, "value" => "402"],
        ["key" => 25, "value" => "403"],
        ["key" => 17, "value" => "410"],
        ["key" => 21, "value" => "411"],
        ["key" => 26, "value" => "412"],
        ["key" => 31, "value" => "413"],
        ["key" => 22, "value" => "420"],
        ["key" => 26, "value" => "421"],
        ["key" => 32, "value" => "422"],
        ["key" => 38, "value" => "423"],
        ["key" => 27, "value" => "430"],
        ["key" => 33, "value" => "431"],
        ["key" => 39, "value" => "432"],
        ["key" => 34, "value" => "440"],
        ["key" => 40, "value" => "441"],
        ["key" => 47, "value" => "442"],
        ["key" => 41, "value" => "450"],
        ["key" => 48, "value" => "451"],
        ["key" => 23, "value" => "500"],
        ["key" => 31, "value" => "501"],
        ["key" => 43, "value" => "502"],
        ["key" => 58, "value" => "503"],
        ["key" => 33, "value" => "510"],
        ["key" => 46, "value" => "511"],
        ["key" => 63, "value" => "512"],
        ["key" => 84, "value" => "513"],
        ["key" => 49, "value" => "520"],
        ["key" => 70, "value" => "521"],
        ["key" => 94, "value" => "522"],
        ["key" => 120, "value" => "523"],
        ["key" => 150, "value" => "524"],
        ["key" => 79, "value" => "530"],
        ["key" => 110, "value" => "531"],
        ["key" => 140, "value" => "532"],
        ["key" => 170, "value" => "533"],
        ["key" => 210, "value" => "534"],
        ["key" => 130, "value" => "540"],
        ["key" => 170, "value" => "541"],
        ["key" => 220, "value" => "542"],
        ["key" => 280, "value" => "543"],
        ["key" => 350, "value" => "544"],
        ["key" => 430, "value" => "545"],
        ["key" => 240, "value" => "550"],
        ["key" => 350, "value" => "551"],
        ["key" => 540, "value" => "552"],
        ["key" => 920, "value" => "553"],
        ["key" => 1600, "value" => "554"]
    ];
}
