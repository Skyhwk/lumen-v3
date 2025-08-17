<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class DirectResistivity {
    public function index($data, $id_parameter, $mdl) {
        $rumus = 1 / $data->hp;

        $processed = [
			'hasil' => $rumus,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
		];
		return $processed;
    }
}