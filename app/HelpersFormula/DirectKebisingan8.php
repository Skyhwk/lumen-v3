<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectKebisingan8{
    public function index($data, $id_parameter, $mdl){ 
        $dataLeqDba = [];
        $dataLeq = []; // hasil $dataLeqDba * 0.1
        $totalr = [];

        for ($i = 0; $i < count($data->total); $i++) {
            $converted = [];
        
            foreach ($data->total[$i] as $value) {
                // Konversi string ke float dan hitung 10^(value * 0.1)
                $converted[] = pow(10, floatval($value) * 0.1);
            }
        
            $sum = array_sum($converted); // jumlah dari semua hasil konversi
        
            // 10 * LOG10((1 / 120) * sum)
            $dataLeqDba[$i] = round(10 * log10((1 / 120) * $sum), 2);
        
            // dikalikan 0.1
            $dataLeq[$i] = round($dataLeqDba[$i] * 0.1, 2);
        
            // hasil akhir = 10^dataLeq[$i]
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