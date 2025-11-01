<?php

namespace App\HelpersFormula;

class Plankton {
    public function index($data, $id_parameter, $mdl){
        $data_input = $data->data_input;
        $fitoplankton = $data_input[0];
        $zooplankton = $data_input[1];

        $fitoplanktonResult = $this->processData($fitoplankton['data'], 30, 'fito');
        $zooplanktonResult = $this->processData($zooplankton['data'], 19, 'zoo');

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
            'result' => json_encode($mergedPlankton)
        ];
    }

    private function processData($data, $taxa, $type){
        $individu = 0;
        $diversitas = 0;

        $param_prosess = [];
        $dataToSingleArray = array_reduce($data, function ($carry, $item) use(&$individu, &$param_prosess, $type) {
            if($type == 'fito'){
                foreach ($item['data'] as $key => $value) {
                    $carry[$key] = $value;
                    $param_prosess[$key] = $value;
                    $individu += $value;
                }
            }else{
                foreach ($item['data'] as $key => $value) {
                    foreach ($value['data'] as $key2 => $value2) {
                        $carry[$key2] = $value2;
                        $param_prosess[$key2] = $value2;
                        $individu += $value2;
                    }
                }
            }
            return $carry;
        }, []);

        foreach ($dataToSingleArray as $key => $item) {
            $processed = number_format(($item != 0) ? (log($item / $individu) / log(2) * ($item / $individu)) : 0, 8);
            if (intval($item) == 0) {
                $taxa -= 1;
            } else {
                $diversitas += abs($processed);
            }
        }

        $h_max = log($taxa) / log(2);
        $equitabilitas = abs($diversitas / $h_max);

        return [
            'individu' => $individu,
            'taxa' => $taxa,
            'diversitas' => number_format($diversitas, 2),
            'h_max' => number_format($h_max, 2),
            'equitabilitas' => number_format($equitabilitas, 2)
        ];
    }
}
