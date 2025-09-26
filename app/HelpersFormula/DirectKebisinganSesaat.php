<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectKebisinganSesaat{
    public function index($data, $id_parameter, $mdl){ 
        for ($i = 0; $i < count($data->totSesaat); $i++) {
            $total = floatval($data->totSesaat[$i]);
            $hasil[$i] = $total * 0.1;
            $hasil2[$i] = (1 * pow(10, $hasil[$i]));
            $final = round(array_sum($hasil2), 2);
        }

        $rerataLM = round((1 / 120) * $final, 1);
        $hasil = round(10 * log10($rerataLM), 1);

        $processed = [
            'final' => $final,
            'rerataLM' => $rerataLM,
            'hasil' => $hasil,
        ];
        return $processed;
    }
}