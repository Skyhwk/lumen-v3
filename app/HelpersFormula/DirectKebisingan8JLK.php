<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectKebisingan8JLK{
    public function index($data, $id_parameter, $mdl){ 
        $dataLeqDba = [];
        $dataLeq = []; // hasil $dataLeqDba * 0.1
        $totalr = [];
        for ($i = 0; $i < count($data->total); $i++) {
            $sum = array_sum($data->total[$i]); // setara SUM(D89:D123)
    
            // 10 * LOG10((1 / 120) * sum)
            $dataLeqDba[$i] = round(10 * log10((1 / 120) * $sum), 2);
            
            // dikalikan 0.1
            $dataLeq[$i] = round($dataLeqDba[$i] * 0.1, 2);
            
            // hasil = 10^dataLeq[$i], sesuai =10^C132
            $totalr[$i] = round(pow(10, $dataLeq[$i]), 2);
        }
        
        $jumlahLeq = round(array_sum($totalr), 2); // Sama dengan =SUM(D132:D139)
        $rerataSum = round((1 / 8) * $jumlahLeq, 2); // Sama dengan =(1/8)*jumlahLeq
        $hasil = round(10 * log10($rerataSum), 1); // Sama dengan =10*LOG((1/8)*jumlahLeq)

        $processed = [
            'jumlah_leq' => $jumlahLeq,
            'hasil' => $hasil
        ];

        return $processed;
    } 
}