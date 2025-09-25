<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class SinarUv {
    public function index($data, $id_parameter, $mdl){
        $mata = json_decode($data->mata);
        $betis = json_decode($data->betis);
        $siku = json_decode($data->siku);
    
        $totsiku = count(array_keys($siku));
        $totbetis = count(array_keys($betis));
        $totmata = count(array_keys($mata));
    
        $nilmata = 0;
        $nilbetis = 0;
        $nilsiku = 0;
    
        foreach ($mata as $idx => $val) {
            foreach ($val as $k => $v) {
                $nilmata += $v;
            }
        }
        foreach ($betis as $idx => $val) {
            foreach ($val as $k => $v) {
                $nilbetis += $v;
            }
        }
        foreach ($siku as $idx => $val) {
            foreach ($val as $k => $v) {
                $nilsiku += $v;
            }
        }
    
        $nilmata_ = number_format($nilmata / $totmata, 4);
        $nilbetis_ = number_format($nilbetis / $totbetis, 4);
        $nilsiku_ = number_format($nilsiku / $totsiku, 4);
    
        // $processed = json_encode(["Mata" => $nilmata_, "Betis" => $nilbetis_, "Siku" => $nilsiku_]);
        $processed = [
            'hasil1' => $nilmata_,
            'hasil2' => $nilsiku_,
            'hasil3' => $nilbetis_
        ];

        return $processed;
    }

}