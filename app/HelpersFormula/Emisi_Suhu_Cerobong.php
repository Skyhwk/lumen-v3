<?php

namespace App\HelpersFormula;

class Emisi_Suhu_Cerobong
{
    public function index($data, $id_parameter, $mdl)
    {
        $hasil = $data->data_t_flue;

        return [
            'hasil' => $hasil,
            'satuan' => "'C"
        ];
    }
}