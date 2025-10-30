<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class EmisiGravimetri
{
    public function index($data, $id_parameter, $mdl)
    {


        $Vs = null;
        $Vstd = null;
        $vl = null;
        $st = null;
        $ks = null;
        $kb = null;
        $w1 = null;
        $w2 = null;


        $C = null;
        $C1 = null;
        $C2 = null;
        $C3 = null;
        $C4 = null;
        $C5 = null;
        $C6 = null;
        $C7 = null;
        $C8 = null;
        $C9 = null;
        $C10 = null;

        $bobot_filter_1 = $data->bobot_filter_1;
        $bobot_filter_2 = $data->bobot_filter_2;
        $bobot_aseton_1 = $data->bobot_aseton_1;
        $bobot_aseton_2 = $data->bobot_aseton_2;

        $A = round(($bobot_filter_1 + $bobot_aseton_1) - ($bobot_filter_2 + $bobot_aseton_2), 4);
        $B = round($data->volume_gas, 4);

        $C1 = round($A / $B, 4);
        $C = round($C1 * 1000, 4);

        $data = [
            'tanggal_terima' => $data->tanggal_terima,
            'suhu' => null,
            'Va' => null,
            'Vs' => null,
            'Vstd' => null,
            'Pa' => null,
            'Pm' => null,
            'Pv' => null,
            't' => null,
            'durasi' => null,
            'flow' => null,
            'vl' => $vl,
            'st' => $st,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'w1' => $w1,
            'w2' => $w2,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
            'C3' => $C3,
            'C4' => $C4,
            'C5' => $C5,
            'C6' => $C6,
            'C7' => $C7,
            'C8' => $C8,
            'C9' => $C9,
            'C10' => $C10,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
        return $data;
    }
}
