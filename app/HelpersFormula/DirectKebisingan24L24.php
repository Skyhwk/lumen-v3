<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectKebisingan24L24
{
    public function index($data, $id_parameter, $mdl)
    {
        $LSTotal = [];
        $LMTotal = [];

        for ($i = 0; $i < count($data->total); $i++) {
            // 1. Hitung LAeq per jam
            $sum = array_sum($data->total[$i]); // seperti SUM(S5:S39)
            $laeq[$i] = round(10 * log10((1 / 120) * $sum), 2); // sesuai =10*LOG10(1/S40*(SUM(...)))

            // 2. Konversi: dikali 0.1 → =L48*0.1
            $laeq_convert[$i] = round($laeq[$i] * 0.1, 4);

            // 3. Bagi ke LS & LM
            if ($i < 16) { // LS
                $LSTotal[] = pow(10, $laeq_convert[$i]); // =10^M48 dst.
            } else { // LM
                $LMTotal[] = pow(10, $laeq_convert[$i]); // =10^M64 dst.
            }
        }

        // 4. Hitung LS
        $totalLS = array_sum($LSTotal);
        $rerataLS = round((1 / 16) * $totalLS, 4);
        $leqLS = round(10 * log10($rerataLS), 1);

        // 5. Hitung LM
        $totalLM = array_sum($LMTotal);
        $rerataLM = round((1 / 8) * $totalLM, 4);
        $leqLM = round(10 * log10($rerataLM), 1);

        // 6. Hitung LSM sesuai Excel: 
        // =10*LOG((1/24)*((16*(10^(0.1*L73)))+(8*(10^(0.1*(L74+5))))))
        $LSM_linear = (16 * pow(10, 0.1 * $leqLS)) + (8 * pow(10, 0.1 * ($leqLM + 5)));
        $rerataLSM = round((1 / 24) * $LSM_linear, 4);
        $hasil = round(10 * log10($rerataLSM), 1);

        $processed = [
            'laeq' => $laeq, // LAeq per jam
            'leqLS' => $leqLS,
            'leqLM' => $leqLM,
            'totalLSM' => round($LSM_linear, 2),
            'rerataLSM' => $rerataLSM,
            'hasil' => $hasil
        ];

        return $processed;

    }
}