<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class Udara_Direct_CO2_LK
{
    public function index($data, $id_parameter, $mdl) {
        $measurements = [];

        foreach ($data->records as $record) {
            if ($record['pengukuran']) {
                $data = json_decode($record['pengukuran'], true);
                if (is_array($data) && !empty($data)) {
                    $total = array_sum($data);
                    $count = count($data);
                    $measurements[] = $total / $count;
                }
            }
        }

        $hasil = !empty($measurements) ?
            number_format(array_sum($measurements) / count($measurements), 3) : 0;

        return [
            'hasil' => $hasil,
            'satuan' => '%'
        ];
    }
}