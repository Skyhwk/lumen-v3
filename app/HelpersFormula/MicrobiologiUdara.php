<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class MicrobiologiUdara
{
    public function index($data, $id_parameter, $mdl)
    {
        try {
            $processed = [];

            $processed['satuan'] = 'CFU/m3';

            $data_pershift = [];
            $total = 0;
            $count = 0;

            foreach ($data->jumlah_coloni as $key => $value) {
                $rumus = $value / $data->volume[$key];
                $nilai = $key + 1;

                // simpan hasil per shift (as array asosiatif tunggal)
                $data_pershift["Shift $nilai"] = round($rumus, 4);

                // akumulasi untuk rata-rata
                $total += $rumus;
                $count++;
            }

            // hitung hasil rata-rata
            $hasil = $count > 0 ? $total / $count : 0;

            $processed['hasil'] = round($hasil, 0);
            $processed['data_pershift'] = $data_pershift;

            return $processed;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
