<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class MicrobiologiUdara
{
	public function index($data, $id_parameter, $mdl)
	{
		try {
			$processed = array();

            $jumlah_coloni = array_sum($data->jumlah_coloni) / count($data->jumlah_coloni);
            $rumus = number_format(($jumlah_coloni / $data->volume), 4);
            $processed['hasil'] = $rumus;

            $processed['satuan'] = 'CFU/m3';

			return $processed;
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage());
		}
	}
}
