<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectHCHO8JugLK {
    public function index($data, $id_parameter, $mdl) {
        $measurements = [];

        foreach ($data as $record) {
            
            if ($record->pengukuran) {
                $data = json_decode($record->pengukuran, true);
                if (is_array($data) && !empty($data)) {
                    $total = array_sum($data);
                    $count = count($data);
                    $measurements[] = $total / $count;
                }
            }
        }

        $average = !empty($measurements) ?
            number_format(array_sum($measurements) / count($measurements), 3) : 0;

        $hasil = number_format($average * 1000, 3);
        
        if (!is_null($mdl) && $hasil < $mdl) {
            $hasil = "<$mdl";
        }

        return [
            'average' => $average,
            'hasil' => $hasil,
            'satuan' => 'μg/m³'
        ];
    }
}