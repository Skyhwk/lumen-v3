<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectHCHO8Jmgm3 {
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

        $c2 = !empty($measurements) ? round(array_sum($measurements) / count($measurements), 4) : 0;
        $c1 = $c2 * 1000;
        $c3 = ($c2 / 24.45) * 30.03;

        return [
            'c1'        => $c1,
            'c2'        => $c2,
            'c3'        => $c3,
            'satuan'    => 'mg/m3'
        ];
    }
}