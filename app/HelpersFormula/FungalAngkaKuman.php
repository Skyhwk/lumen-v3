<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class FungalAngkaKuman {

    public function index($data, $id_parameter, $mdl) {

        // C = Jumlah Koloni (CFU) / Volume udara (mL)
        $rumus = number_format(((array_sum($data->jumlah_coloni) / count($data->jumlah_coloni)) / $data->volume), 2);

        $satuan = 'CFU/m3';

        $processed = [
            'id_microbio_header' => $data->id_header,
            'no_sampel' => $data->no_sample,
            'tanggal_terima' => $data->tgl_terima,
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
