<?php

namespace App\HelpersFormula;

class Plankton {
    public function index($data, $id_parameter, $mdl){
        $data_input = $data->data_input;
        $fitoplankton = $data_input[0];
        $zooplankton = $data_input[1];

        $fitoplanktonResult = $this->processData($fitoplankton['data'], 30, 'fito');
        $zooplanktonResult = $this->processData($zooplankton['data'], 19, 'zoo');

        $fitoplankton['data'] = $fitoplanktonResult['data'];
        $zooplankton['data'] = $zooplanktonResult['data'];

        $fitoplankton['result'] = [
            'individu' => $fitoplanktonResult['individu'],
            'taxa' => $fitoplanktonResult['taxa'],
            'diversitas' => $fitoplanktonResult['diversitas'],
            'h_max' => $fitoplanktonResult['h_max'],
            'equitabilitas' => $fitoplanktonResult['equitabilitas']
        ];

        $zooplankton['result'] = [
            'individu' => $zooplanktonResult['individu'],
            'taxa' => $zooplanktonResult['taxa'],
            'diversitas' => $zooplanktonResult['diversitas'],
            'h_max' => $zooplanktonResult['h_max'],
            'equitabilitas' => $zooplanktonResult['equitabilitas']
        ];

        # Data ini disimpan ke ws value
        $mergedPlankton = [$fitoplankton, $zooplankton];

        return [
            'result' => $mergedPlankton
        ];
    }

    private function processData($data, $taxa, $type)
    {
        $individu = 0;
        $diversitas = 0;

        /**
         * STEP 1
         * Ambil semua value (flatten) untuk menghitung total individu
         */
        $singleValues = array_reduce($data, function ($carry, $item) use (&$individu, $type) {

            if ($type === 'fito') {

                foreach ($item['data'] as $key => $value) {
                    $carry[$key] = floatval($value);
                    $individu += floatval($value);
                }

            } else { // zoo

                foreach ($item['data'] as $key => $value) {
                    foreach ($value['data'] as $key2 => $value2) {
                        $carry[$key2] = floatval($value2);
                        $individu += floatval($value2);
                    }
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
         * Kembalikan ke struktur ASLI (fito / zoo)
         */
        $finalStructure = $data; // clone struktur awal

        if ($type === 'fito') {

            foreach ($finalStructure as &$item) {
                foreach ($item['data'] as $key => $v) {
                    $item['data'][$key] = $singleValues[$key];
                }
            }

        } else { // zoo, 3 level

            foreach ($finalStructure as &$item) {
                foreach ($item['data'] as &$sub1) {
                    foreach ($sub1['data'] as $key => $v) {
                        $sub1['data'][$key] = $singleValues[$key];
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
