<?php

namespace App\HelpersFormula;

class Necton
{
    public function index($data, $id_parameter, $mdl)
    {
        $data_input = $data->data_input;
        $necton = $data_input;

        $nectonResult = $this->processData($necton, 12);

        return [
            [
                'data' => $nectonResult['data'],
                'result' => [
                    'individu' => $nectonResult['individu'],
                    'taxa' => $nectonResult['taxa'],
                    'diversitas' => $nectonResult['diversitas'],
                    'h_max' => $nectonResult['h_max'],
                    'equitabilitas' => $nectonResult['equitabilitas']
                ]
            ]
        ];
    }

    private function processData($data, $taxa)
    {
        $individu = 0;
        $diversitas = 0;

        /**
         * STEP 1
         * Ambil semua value (flatten) untuk menghitung total individu
         * Necton memiliki 2 tipe struktur: Fishes (4 level) dan Non-Fishes (3 level)
         */
        $singleValues = [];

        foreach ($data as $item) {
            if ($item['name'] == 'Fishes') {
                // Struktur 4 level: data → data → data → data
                foreach ($item['data'] as $value) {
                    foreach ($value['data'] as $species => $count) {
                        foreach ($count['data'] as $key => $values) {
                            $singleValues[$key] = floatval($values);
                            $individu += floatval($values);
                        }
                    }
                }
            } else {
                // Struktur 3 level: data → data → data
                foreach ($item['data'] as $value) {
                    foreach ($value['data'] as $species => $count) {
                        $singleValues[$species] = floatval($count);
                        $individu += floatval($count);
                    }
                }
            }
        }

        /**
         * STEP 2
         * Hitung processed, update singleValues
         */
        foreach ($singleValues as $key => $val) {
            $processed = ($individu > 0 && $val > 0)
                ? (log($val / $individu) / log(2) * ($val / $individu))
                : 0;

            $singleValues[$key] = [
                'hasil_uji' => $val,
                'hasil_perkalian' => round($processed, 8)
            ];

            if ($val == 0) {
                $taxa -= 1;
            } else {
                $diversitas += abs($processed);
            }
        }

        /**
         * STEP 3
         * Kembalikan ke struktur ASLI necton (mixed: 3 & 4 level)
         */
        $finalStructure = $data; // clone struktur awal

        foreach ($finalStructure as &$item) {
            if ($item['name'] == 'Fishes') {
                // Kembalikan ke struktur 4 level
                foreach ($item['data'] as &$value) {
                    foreach ($value['data'] as &$species) {
                        foreach ($species['data'] as $key => $v) {
                            $species['data'][$key] = $singleValues[$key];
                        }
                    }
                }
            } else {
                // Kembalikan ke struktur 3 level
                foreach ($item['data'] as &$value) {
                    foreach ($value['data'] as $key => $v) {
                        $value['data'][$key] = $singleValues[$key];
                    }
                }
            }
        }

        /**
         * STEP 4
         * Hitung nilai akhir
         */
        $h_max = log($taxa) / log(2);
        $equitabilitas = abs($diversitas / $h_max);

        return [
            'individu' => $individu,
            'taxa' => $taxa,
            'diversitas' => number_format($diversitas, 2),
            'h_max' => number_format($h_max, 2),
            'equitabilitas' => number_format($equitabilitas, 2),
            'data' => $finalStructure
        ];
    }
}
