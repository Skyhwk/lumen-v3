<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class FungalAngkaKuman {

    public function index($data, $id_parameter, $mdl) {

        // C = Jumlah Koloni (CFU) / Volume udara (mL)
        if(floatval(array_sum($data->volume) / count($data->volume)) > 0 ){
            $rumus = number_format(((array_sum($data->jumlah_coloni) / count($data->jumlah_coloni)) / (array_sum($data->volume) / count($data->volume))), 2);
        }else{
            $rumus = 0;
        }

        $satuan = 'CFU/m3';

        $processed = [
            'no_sampel' => $data->no_sample,
            // 'flow' => $data->flow,
            // 'durasi' => $data->durasi,
            // 'tekanan_u' => $data->Tekanan,
            // 'suhu' => $data->Suhu,
            // 'jumlah_coloni' => $data->jumlah_coloni,
            // 'volume_udara' => $data->volume,
            'satuan' => $satuan,
            'hasil' => $rumus
        ];

        return $processed;
    }

}
