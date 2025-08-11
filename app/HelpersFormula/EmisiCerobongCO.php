<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class EmisiCerobongCO
{
    public function index($data, $id_parameter, $mdl){
        $hasil = (($data->C / 28.01)) * 24.45 * ($data->Pa / $data->Ta) * (298 / 760);
        return [
            'hasil' => $hasil
        ];
    }
}