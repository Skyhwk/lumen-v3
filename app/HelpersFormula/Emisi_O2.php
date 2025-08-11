<?php

namespace App\HelpersFormula;

class Emisi_O2
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