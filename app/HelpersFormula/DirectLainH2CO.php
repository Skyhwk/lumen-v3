<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectLainH2CO {
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
        $average = $totalNilai / $jumlahElemen;
        $hasil = $average * 1000;
        $hasil = $hasil < 1 ? "<1" : number_format($hasil, 4);
        return [
            'hasil' => $hasil,
            'satuan' => 'ug/Nm3'
        ];
    }
}