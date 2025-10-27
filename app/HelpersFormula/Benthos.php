<?php

namespace App\HelpersFormula;

class Benthos {
    public function index($data, $id_parameter, $mdl){
        $data_input = $data->data_input;
        $benthos = $data_input[0];

        $benthosResult = $this->processData($benthos['data'], 11);

        $benthos['result'] = [
            'individu' => $benthosResult['individu'],
            'taxa' => $benthosResult['taxa'],
            'diversitas' => $benthosResult['diversitas'],
            'h_max' => $benthosResult['h_max'],
            'equitabilitas' => $benthosResult['equitabilitas']
        ];

        return [
            'result' => json_encode($benthos)
        ];
    }

    private function processData($data, $taxa){
        $individu = 0;
        $diversitas = 0;

        $param_prosess = [];
        $dataToSingleArray = array_reduce($data, function ($carry, $item) use(&$individu, &$param_prosess) {
            foreach ($item['data'] as $key => $value) {
                foreach ($value['data'] as $key2 => $value2) {
                    $carry[$key2] = $value2;
                    $param_prosess[$key2] = $value2;
                    $individu += $value2;
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
