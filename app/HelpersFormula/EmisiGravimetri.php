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
        /**
         * C (mg/Nm3) = A / B * 1000
         * A = ((C2 - C1) + (D2 - D1)) - ((E2 - E1) + (F2 - F1))
         * 
         * Inputan Analis
         * C1 = Bobot konstan sampel Filter awal(gram)
         * C2 = Bobot konstan sampel filter akhir (gram)
         * D1 = Bobot konstan sampel Aseton Awal (gram)
         * D2 = Bobot konstan sampel Aseton Akhir (gram)
         * E1 = Bobot konstan Blanko Filter Awal (gram)
         * E2 = Bobot Konstan blanko filter Akhir (gram)
         * F1 = Bobot konstan Blanko Aseton awal (gram)
         * F2 = Bobot konstan Blanko Aseton Akhir (gram)
         */

        $bobot_filter = ($data->bobot_sampel_akhir - $data->bobot_sampel_awal);
        $bobot_aseton = ($data->bobot_aseton_akhir - $data->bobot_aseton_awal);
        $blanko_filter = ($data->blanko_filter_akhir - $data->blanko_filter_awal);
        $blanko_aseton = ($data->blanko_aseton_akhir - $data->blanko_aseton_awal);

        $A = round(($bobot_filter + $bobot_aseton) - ($blanko_filter + $blanko_aseton), 4);
        $B = round($data->volume_gas, 4);

        $C1 = round(($A / $B) * 1000, 4);
        $C = round($C1 * 1000, 4);

        $C3 = $C;
        $C4 = $C1;

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
