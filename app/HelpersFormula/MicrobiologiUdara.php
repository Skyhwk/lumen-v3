<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class MicrobiologiUdara
{
	public function index($data, $id_parameter, $mdl)
	{
		try {
			$processed = array();

            $processed['satuan'] = 'CFU/m3';

            $data_pershift = [];
            foreach ($data->jumlah_coloni as $key => $value) {
                // $jumlah_coloni = array_sum($data->jumlah_coloni) / count($data->jumlah_coloni);
                $rumus = number_format(($value / $data->volume), 4);

                $data_pershift[] = $rumus;
            }

            $hasil = array_sum($data_pershift) / count($data_pershift);

            $processed['hasil'] = number_format($hasil, 4);

            $processed['data_pershift'] = $data_pershift;

			return $processed;
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage());
		}
	}
}
