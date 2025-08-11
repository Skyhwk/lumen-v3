<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class EmisiCerobongSO2
{
    public function index($data, $id_parameter, $mdl){
        $hasil = (($data->C / 64.066)) * 24.45 * ($data->Pa / $data->Ta) * (298 / 760);
        return [
            'hasil' => $hasil
        ];
    }
}