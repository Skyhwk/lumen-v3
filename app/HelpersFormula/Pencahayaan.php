<?php
namespace App\HelpersFormula;
use Carbon\Carbon;
class Pencahayaan
{
    public function index($data, $id_parameter, $mdl){
        $total = [];
        foreach ($data->nilai as $x) {
            array_push($total, explode(';', $x)[0]);
        }

        $rata_rata = array_sum($total);
        $hitung = $rata_rata / count($data->nilai);
        $hasil = number_format($hitung, 2);

        return [
            'hasil' => $hasil,
            'satuan' => 'Lux'
        ];
    }
}