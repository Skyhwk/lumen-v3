<?php

namespace App\HelpersFormula;

class Benthos {
    public function index($data, $id_parameter, $mdl){
        $data_input = $data->data_input;
        $benthos = $data_input[0];

        $benthosResult = $this->processData($benthos['data'], 11);

        // Update struktur data benthos dengan hasil processed
        $benthos['data'] = $benthosResult['data'];

        $benthos['result'] = [
            'individu' => $benthosResult['individu'],
            'taxa' => $benthosResult['taxa'],
            'diversitas' => $benthosResult['diversitas'],
            'h_max' => $benthosResult['h_max'],
            'equitabilitas' => $benthosResult['equitabilitas']
        ];

        // Data ini disimpan ke ws value
        return [
            'result' => [$benthos]
        ];
    }

    private function processData($data, $taxa){
        $individu = 0;
        $diversitas = 0;

        /**
         * STEP 1
         * Ambil semua value (flatten) untuk menghitung total individu
         */
        $singleValues = array_reduce($data, function ($carry, $item) use (&$individu) {
            foreach ($item['data'] as $key => $value) {
                foreach ($value['data'] as $key2 => $value2) {
                    $carry[$key2] = floatval($value2);
                    $individu += floatval($value2);
                }
            }
            return $carry;
        }, []);

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
         * Kembalikan ke struktur ASLI benthos (3 level)
         */
        $finalStructure = $data; // clone struktur awal

        foreach ($finalStructure as &$item) {
            foreach ($item['data'] as &$sub1) {
                foreach ($sub1['data'] as $key => $v) {
                    $sub1['data'][$key] = $singleValues[$key];
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