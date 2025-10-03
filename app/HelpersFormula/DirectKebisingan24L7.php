<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectKebisingan24L7 {
    public function index($data, $id_parameter, $mdl){
        $LSTotal = [];
        $LMTotal = [];

        for ($i = 0; $i < count($data->total); $i++) {
            $lm[$i] = round((1 / 120) * array_sum($data->total[$i]), 2);

            if ($i == 0 || $i == 2) {
                // L1 || L3 
                $totalganjilLs[$i] = round(3 * pow(10, 0.1 * $lm[$i]), 2);
                array_push($LSTotal, $totalganjilLs[$i]);
            } else if ($i == 1 || $i == 3) {
                // L2 || L4
                $totalgenapLs[$i] = round(5 * pow(10, 0.1 * $lm[$i]), 2);
                array_push($LSTotal, $totalgenapLs[$i]);
            } else if ($i == 4) {
                // L5
                $totalgenapLm[$i] = round(2 * pow(10, 0.1 * $lm[$i]), 2);
                array_push($LMTotal, $totalgenapLm[$i]);
            } else if ($i == 5 || $i == 6) {
                // L6 || L7
                $totalgenapLm[$i] = round(3 * pow(10, 0.1 * $lm[$i]), 2);
                array_push($LMTotal, $totalgenapLm[$i]);
            }

        }

        $totalLS = array_sum($LSTotal);
        $rerataLS = round((1 / 16) * $totalLS, 2);
        $leqLS = round(10 * log10($rerataLS), 1);

        $totalLM = array_sum($LMTotal);
        $rerataLM = round((1 / 8) * $totalLM, 2);
        $leqLM = round(10 * log10($rerataLM), 1);

        $totalLSM = round(16 * pow(10, 0.1 * $leqLS) + 8 * pow(10, 0.1 * ($leqLM + 5)), 2);
        $rerataLSM = round((1 / 24) * $totalLSM, 4);
        $hasil = round(10 * log10($rerataLSM), 1);

        $processed = [
            'totalLSM' => $totalLSM,
            'rerataLSM' => $rerataLSM,
            'leqLS' => $leqLS,
            'leqLM' => $leqLM,
            'hasil' => $hasil,
            'satuan' => 'dB'
        ];

        return $processed;
    }
}