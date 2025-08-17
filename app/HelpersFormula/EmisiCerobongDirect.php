<?php

namespace App\HelpersFormula;

class EmisiCerobongDirect {
    public function index($data, $id_parameter, $mdl){
        $hasil = $data->C;
        
        return [
            'hasil' => $hasil
        ];
    }
}