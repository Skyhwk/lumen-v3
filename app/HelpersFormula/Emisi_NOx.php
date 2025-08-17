<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Emisi_NOx
{
    public function index($data, $id_parameter, $mdl)
    {
        $hasil = (($data->C / 46) * 24.45) * ($data->Pa / $data->Ta) * (298 / 760);
        return [
            'hasil' => $hasil,
            'satuan' => 'mg/Nm3'
        ];
    }
}