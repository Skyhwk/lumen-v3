<?php

namespace App\HelpersFormula;

class Emisi_O2
{
    public function index($data, $id_parameter, $mdl)
    {
        $hasil = $data->C;

        // if ($hasil < 0.1) {
        //     $hasil = '<0.1';
        // } else {
        // }
        $hasil = round($hasil, 1);

        return [
            'hasil' => $hasil,
            'satuan' => '%'
        ];
    }
}