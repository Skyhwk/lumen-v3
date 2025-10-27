<?php

namespace App\HelpersFormula;

class Necton {
    public function index($data, $id_parameter, $mdl){
        $data_input = $data->data_input;
        $necton = $data_input;
        $nectonResult = $this->processData($necton, 12);

        $necton['result'] = [
            'individu' => $nectonResult['individu'],
            'taxa' => $nectonResult['taxa'],
            'diversitas' => $nectonResult['diversitas'],
            'h_max' => $nectonResult['h_max'],
            'equitabilitas' => $nectonResult['equitabilitas']
        ];

        return [
            'result' => json_encode($necton)
        ];
    }

    private function processData($data, $taxa){
        $individu = 0;
        $diversitas = 0;

        $param_prosess = [];
        $dataToSingleArray = [];

        foreach ($data as $item) {
            if($item['name'] == 'Fishes'){
                foreach ($item['data'] as $value) {
                    foreach ($value['data'] as $species => $count) {
                        foreach ($count['data'] as $key => $values) {
                            $dataToSingleArray[$key] = $values;
                            $param_prosess[$key] = $values;
                            $individu += $values;
                        }
                    }
                }
            }else{
                foreach ($item['data'] as $value) {
                    foreach ($value['data'] as $species => $count) {
                        $dataToSingleArray[$species] = $count;
                        $param_prosess[$species] = $count;
                        $individu += $count;
                    }
                }
            }
        }

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
