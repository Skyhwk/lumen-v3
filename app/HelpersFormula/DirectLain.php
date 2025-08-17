<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLain {
    public function index($data, $id_parameter, $mdl) {
        $totalNilai = 0;
        $jumlahElemen = 0;
        foreach ($data as $data) {
            $pengukuran = json_decode($data->pengukuran, true); // jadi array

            foreach ($pengukuran as $key => $value) {
                $totalNilai += floatval($value);
                $jumlahElemen++;
            }
        }
        $average = $jumlahElemen > 0 ? number_format($totalNilai / $jumlahElemen, 4, '.', ',') : 0;
        return [
            'hasil' => $average
        ];
    }
}