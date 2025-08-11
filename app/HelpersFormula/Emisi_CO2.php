<?php

namespace App\HelpersFormula;

class Emisi_CO2
{
    public function index($data, $id_parameter, $mdl)
    {
        $hasil = $data->C;

        return [
            'hasil' => $hasil,
            'satuan' => '%'
        ];
    }
}